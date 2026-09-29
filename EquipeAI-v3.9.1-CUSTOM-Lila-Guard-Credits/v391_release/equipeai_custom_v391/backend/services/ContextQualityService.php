<?php
namespace App\services;

final class ContextQualityService {
    public const ACCEPTABLE_SCORE=82;

    public function evaluate(string $field,string $value):array{
        $text=trim(preg_replace('/[ \t]+/u',' ',$value)??'');
        if($text==='')return ['score'=>0,'accepted'=>false,'level'=>'empty','missing'=>['preencher o campo']];
        $score=20;$missing=[];
        $len=$this->textLen($text);$clauses=$this->clauses($text);$items=$this->items($text);
        switch($field){
            case 'segment':
                $words=preg_split('/\s+/u',$text,-1,PREG_SPLIT_NO_EMPTY)?:[];$score=22;
                if(count($words)>=2)$score+=28;else $missing[]='especificar a especialidade ou nicho';
                if(count($words)>=3&&count($words)<=9)$score+=18;
                if(!preg_match('/^(sa[uú]de|servi[cç]os?|com[eé]rcio|tecnologia|educa[cç][aã]o|beleza|marketing|consultoria|neg[oó]cios?|empresa)$/iu',trim($text," \t\n\r\0\x0B.,;:")))$score+=24;else $missing[]='trocar o segmento genérico por um nicho mais específico';
                if(preg_match('/\b(est[eé]tica|odontol[oó]gica|cl[ií]nica|software|cont[aá]bil|jur[ií]dica|industrial|varejo|b2b|b2c|especializada|especializado)\b/iu',$text))$score+=8;
                break;
            case 'description':
                $score=min(40,(int)round($len/4));
                $this->signal($text,'/\b(oferece|atua|especializ|desenvolve|vende|presta|atende|realiza|produz|fornece|empresa|cl[ií]nica|ag[eê]ncia|escrit[oó]rio)\b/iu',18,'explicar o que a empresa faz',$score,$missing);
                $this->signal($text,'/\b(clientes?|pacientes?|empresas?|adultos?|fam[ií]lias?|profissionais?|p[uú]blico|neg[oó]cios?|consumidores?)\b/iu',18,'indicar para quem a empresa atende',$score,$missing);
                $this->signal($text,'/\b(com foco|atrav[eé]s|por meio|atendimento|acompanhamento|processo|metodologia|especializada|especializado|personalizado|digital|presencial|online)\b/iu',18,'explicar como trabalha ou qual é o foco',$score,$missing);
                if($clauses>=2)$score+=8;
                break;
            case 'business_hours':
                $score=12;
                $this->signal($text,'/\b(segunda|ter[cç]a|quarta|quinta|sexta|s[aá]bado|domingo|seg\.?|ter\.?|qua\.?|qui\.?|sex\.?)\b/iu',35,'informar os dias de atendimento',$score,$missing);
                $this->signal($text,'/\b\d{1,2}([:h]\d{0,2})?\s*(?:h|horas?)?\b/iu',38,'informar os horários',$score,$missing);
                if(preg_match('/\b(feriado|agendamento|exceto|plant[aã]o|intervalo|almo[cç]o|fechado|somente)\b/iu',$text))$score+=15;
                break;
            case 'products':
                $score=min(45,(int)round($len/4));
                if(count($items)>=2||$clauses>=2)$score+=30;else $missing[]='listar pelo menos duas soluções, linhas ou categorias quando existirem';
                if($this->wordCount($text)>=12)$score+=18;else $missing[]='dar uma breve descrição do que é oferecido';
                if(preg_match('/\b(para|inclui|indicado|voltado|solu[cç][aã]o|servi[cç]o|produto)\b/iu',$text))$score+=7;
                break;
            case 'target_audience':
                $score=min(34,(int)round($len/5));
                $this->signal($text,'/\b(adultos?|jovens?|fam[ií]lias?|empresas?|neg[oó]cios?|profissionais?|gestores?|pacientes?|clientes?|homens?|mulheres?|b2b|b2c|pequenas|m[eé]dias|grandes)\b/iu',24,'definir o perfil principal do público',$score,$missing);
                $this->signal($text,'/\b(buscam|precisam|querem|necessitam|interessad|objetivo|dor|desafio|melhorar|resolver|contratar|comprar)\b/iu',24,'explicar a principal necessidade ou intenção desse público',$score,$missing);
                $this->signal($text,'/\b(anos?|regi[aã]o|cidade|estado|brasil|r\$|renda|setor|segmento|momento|particular|online|presencial)\b/iu',18,'adicionar um recorte útil, como região, perfil ou momento de compra',$score,$missing);
                break;
            case 'differentials':
                $score=min(38,(int)round($len/5));
                $this->signal($text,'/\b(atendimento|prazo|tecnologia|equipamento|acompanhamento|suporte|certifica[cç][aã]o|experi[eê]ncia|processo|metodologia|entrega|equipe|especialista|hora marcada|personalizado|digital)\b/iu',30,'trocar elogios genéricos por diferenciais concretos',$score,$missing);
                if($clauses>=2||count($items)>=2)$score+=24;else $missing[]='informar pelo menos dois diferenciais quando existirem';
                if($clauses>=3||count($items)>=3)$score+=8;
                if(preg_match('/\b(melhor|n[uú]mero 1|incompar[aá]vel|perfeito|garantido)\b/iu',$text))$score-=12;
                break;
            case 'avoid_words':
            case 'preferred_words':
                $score=24;
                if(count($items)>=2||$clauses>=2||preg_match('/[,;]/u',$text))$score+=52;else $missing[]='adicionar mais de um exemplo ou regra';
                if($len>=35)$score+=18;
                if(preg_match('/\b(n[aã]o|evitar|usar|preferir|chamar|dizer|termo|express[aã]o)\b/iu',$text))$score+=6;
                break;
            case 'specific_rules':
                $score=min(34,(int)round($len/5));
                $this->signal($text,'/\b(n[aã]o|nunca|sempre|deve|evitar|quando|somente|jamais|priorizar|orientar)\b/iu',30,'escrever regras objetivas: “sempre…”, “nunca…”, “quando…”',$score,$missing);
                if($clauses>=2||count($items)>=2)$score+=28;else $missing[]='adicionar pelo menos duas regras quando necessário';
                break;
            default:
                $score=min(100,20+(int)round(min(80,$len/2)));
                if($len<45)$missing[]='adicionar mais contexto útil';
        }
        $score=max(8,min(100,(int)round($score)));
        $level=$score<30?'very-basic':($score<self::ACCEPTABLE_SCORE?'basic':($score<92?'good':'excellent'));
        return ['score'=>$score,'accepted'=>$score>=self::ACCEPTABLE_SCORE,'level'=>$level,'missing'=>array_slice(array_values(array_unique($missing)),0,3)];
    }

    public function rubric(string $field):string{
        return match($field){
            'segment'=>'Seja específico em 2 a 8 palavras. Evite categorias amplas como “saúde”, “serviços” ou “tecnologia” quando houver dados que permitam definir o nicho.',
            'description'=>'Deve deixar claro o que a empresa faz, para quem atende e como/foco de atuação. Prefira 2 ou 3 frases objetivas.',
            'business_hours'=>'Deve conter dias e horários de atendimento; inclua exceções somente se estiverem nas fontes.',
            'products'=>'Organize produtos/soluções reais em uma lista curta ou frase estruturada, com breve descrição quando houver fonte.',
            'target_audience'=>'Defina perfil principal, necessidade/intenção e algum recorte real disponível (localização, setor, faixa ou momento de compra).',
            'differentials'=>'Liste diferenciais concretos e verificáveis. Evite elogios vazios e superlativos.',
            'avoid_words'=>'Transforme as informações em regras/exemplos claros do que evitar, preferencialmente dois ou mais itens.',
            'preferred_words'=>'Transforme as informações em regras/exemplos claros do vocabulário preferido, preferencialmente dois ou mais itens.',
            'specific_rules'=>'Escreva regras operacionais acionáveis usando “sempre”, “nunca”, “quando” ou equivalentes, sem criar políticas.',
            'additional_info'=>'Organize apenas informações adicionais realmente úteis para atendimento ou comunicação.',
            default=>'Torne o texto claro, específico e utilizável sem criar fatos.'
        };
    }

    private function signal(string $text,string $regex,int $points,string $missingText,int &$score,array &$missing):void{
        if(preg_match($regex,$text))$score+=$points;else $missing[]=$missingText;
    }
    private function clauses(string $text):int{return count(array_filter(preg_split('/[.;\n]|\s+-\s+/u',$text)?:[],fn($v)=>$this->textLen(trim($v))>4));}
    private function items(string $text):array{return array_values(array_filter(array_map('trim',preg_split('/\n|;|•|\|/u',$text)?:[])));}
    private function wordCount(string $text):int{return preg_match_all('/[\p{L}\p{N}]+/u',$text,$m)?:0;}
    private function textLen(string $text):int{return function_exists('mb_strlen')?mb_strlen($text,'UTF-8'):strlen($text);}
}
