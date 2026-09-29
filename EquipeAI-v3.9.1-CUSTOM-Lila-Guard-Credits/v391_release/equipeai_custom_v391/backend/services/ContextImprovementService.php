<?php
namespace App\services;

use App\config\Database;
use App\providers\ProviderFactory;
use App\repositories\CompanyRepository;
use App\utils\Logger;
use RuntimeException;

final class ContextImprovementService {
    private ContextQualityService $quality;
    public function __construct(){ $this->quality=new ContextQualityService(); }

    public function improve(array $user,string $field,string $value,string $label,array $draft=[]):array{
        $pdo=Database::get();
        $model=$pdo->query("SELECT p.id provider_id,p.slug,m.id model_id,m.model_name,m.input_cost_per_million,m.output_cost_per_million,m.cost_currency FROM ai_providers p JOIN ai_models m ON m.provider_id=p.id WHERE p.slug='openai' AND p.active=1 AND m.active=1 ORDER BY (COALESCE(m.input_cost_per_million,999999)+COALESCE(m.output_cost_per_million,999999)) ASC,m.id ASC LIMIT 1")->fetch();
        if(!$model)throw new RuntimeException('Nenhum modelo OpenAI está ativo para o assistente de contexto.');

        $repo=new CompanyRepository();
        $company=$repo->find((int)$user['company_id'])??[];
        foreach($draft as $key=>$draftValue){if(trim((string)$draftValue)!=='')$company[$key]=$draftValue;}
        $services=$repo->services((int)$user['company_id']);
        $sources=$this->sourceContext($field,$company,$services,$value);
        $before=$this->quality->evaluate($field,$value);
        $rubric=$this->quality->rubric($field);
        $targetScore=max(88,min(92,$before['score']+10));

        $best=['text'=>$value,'score'=>$before['score'],'accepted'=>false,'missing'=>$before['missing']];
        $totalIn=0;$totalOut=0;$passes=0;$started=microtime(true);
        for($pass=1;$pass<=2;$pass++){
            $passes=$pass;
            $repair=$pass===2?"\nA primeira tentativa obteve {$best['score']}/100. O alvo é pelo menos {$targetScore}/100. Corrija especificamente estes pontos: ".implode('; ',$best['missing']).". Não aumente o texto sem necessidade.":'';
            $messages=[
                ['role'=>'system','content'=>"Você é um arquiteto de contexto empresarial. Sua tarefa é transformar um campo fraco em conteúdo operacional pronto para uso por agentes de IA. Use SOMENTE fatos suportados pelas FONTES. Você pode sintetizar uma categoria óbvia a partir dos serviços e dados fornecidos, mas não pode inventar preço, localização, público, diferencial, política, certificação, resultado ou característica. Escreva em português do Brasil, sem floreios, sem linguagem publicitária e sem explicar seu raciocínio. {$rubric} Retorne APENAS JSON válido no formato {\"text\":\"...\",\"missing\":[\"...\"]}. O campo text deve ficar com no máximo 500 caracteres. A melhoria só será aceita pelo sistema se atingir pelo menos {$targetScore}/100 na rubrica, então faça uma melhoria substancial, não apenas cosmética."],
                ['role'=>'user','content'=>"CAMPO: {$label}\nTEXTO ATUAL:\n{$value}\n\nFONTES CONFIÁVEIS DA EMPRESA:\n{$sources}{$repair}"]
            ];
            $result=ProviderFactory::make('openai')->generate((string)$model['model_name'],$messages,['temperature'=>.18,'max_tokens'=>420]);
            $totalIn+=(int)($result['input_tokens']??0);$totalOut+=(int)($result['output_tokens']??0);
            $parsed=$this->parseJson((string)($result['text']??''));
            $candidate=trim((string)($parsed['text']??''));
            if($candidate==='')continue;
            $evaluation=$this->quality->evaluate($field,$candidate);
            $missing=array_values(array_unique(array_merge($evaluation['missing'],array_filter(array_map('strval',(array)($parsed['missing']??[]))))));
            $candidateAccepted=$evaluation['score']>=$targetScore;
            if($evaluation['score']>$best['score'])$best=['text'=>$candidate,'score'=>$evaluation['score'],'accepted'=>$candidateAccepted&&($evaluation['score']-$before['score']>=8),'missing'=>array_slice($missing,0,3)];
            if($candidateAccepted&&($evaluation['score']-$before['score']>=8))break;
        }

        if($best['text']===$value)throw new RuntimeException('A IA não conseguiu produzir uma melhoria real com as informações disponíveis.');
        $cost=(new CostService())->estimateBrl($model,$totalIn,$totalOut);
        $pdo->prepare('INSERT INTO ai_usage_logs(user_id,agent_run_id,provider_id,model_id,input_tokens,output_tokens,estimated_cost_brl,created_at) VALUES(?,NULL,?,?,?,?,?,NOW())')->execute([(int)$user['id'],(int)$model['provider_id'],(int)$model['model_id'],$totalIn,$totalOut,$cost]);
        Logger::write('ai_requests','context_improvement',['user_id'=>(int)$user['id'],'company_id'=>(int)$user['company_id'],'field'=>$field,'model'=>$model['model_name'],'before_score'=>$before['score'],'after_score'=>$best['score'],'accepted'=>$best['accepted'],'passes'=>$passes,'input_tokens'=>$totalIn,'output_tokens'=>$totalOut,'duration_ms'=>(int)((microtime(true)-$started)*1000),'cost_brl'=>$cost]);

        return [
            'text'=>$best['text'],'score'=>$best['score'],'accepted'=>$best['accepted'],'missing'=>$best['missing'],
            'previous_score'=>$before['score'],'target_score'=>$targetScore,'improvement'=>max(0,$best['score']-$before['score']),'passes'=>$passes,
            'usage'=>['input_tokens'=>$totalIn,'output_tokens'=>$totalOut]
        ];
    }

    private function sourceContext(string $field,array $company,array $services,string $value):string{
        $map=[
            'segment'=>['name','description','products','target_audience'],
            'description'=>['name','segment','products','target_audience','differentials','additional_info'],
            'business_hours'=>['business_hours','additional_info','address'],
            'products'=>['segment','description','products','target_audience'],
            'target_audience'=>['segment','description','products','differentials','address'],
            'differentials'=>['segment','description','products','differentials','additional_info'],
            'avoid_words'=>['communication_tone','avoid_words','preferred_words','specific_rules'],
            'preferred_words'=>['communication_tone','avoid_words','preferred_words','specific_rules'],
            'specific_rules'=>['communication_tone','avoid_words','preferred_words','specific_rules','additional_info'],
            'additional_info'=>['segment','description','business_hours','products','target_audience','differentials','specific_rules','additional_info'],
        ];
        $keys=$map[$field]??array_keys($company);$lines=[];
        foreach($keys as $key){$v=trim((string)($company[$key]??''));if($v!==''&&$v!==$value)$lines[]=$key.': '.$this->cut($v,500);}
        if(in_array($field,['segment','description','products','target_audience','differentials','additional_info'],true)){
            $serviceLines=[];
            foreach(array_slice($services,0,8) as $service){if(empty($service['active']))continue;$part=trim((string)($service['name']??''));if(!empty($service['description']))$part.=' — '.$this->cut((string)$service['description'],180);if($part!=='')$serviceLines[]=$part;}
            if($serviceLines)$lines[]='serviços cadastrados: '.implode(' | ',$serviceLines);
        }
        if(!$lines)$lines[]='Nenhum outro contexto confiável foi cadastrado.';
        return implode("\n",$lines);
    }

    private function cut(string $text,int $max):string{return function_exists('mb_substr')?mb_substr($text,0,$max,'UTF-8'):substr($text,0,$max);}

    private function parseJson(string $raw):array{
        $text=trim($raw);
        $text=preg_replace('/^```(?:json)?\s*|\s*```$/i','',$text)??$text;
        $decoded=json_decode($text,true);
        if(is_array($decoded))return $decoded;
        if(preg_match('/\{.*\}/s',$text,$m)){$decoded=json_decode($m[0],true);if(is_array($decoded))return $decoded;}
        return ['text'=>$text,'missing'=>[]];
    }
}
