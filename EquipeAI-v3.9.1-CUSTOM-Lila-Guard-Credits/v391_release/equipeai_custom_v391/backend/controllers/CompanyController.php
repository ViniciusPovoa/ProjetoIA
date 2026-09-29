<?php
namespace App\controllers;

use App\config\Database;
use App\repositories\CompanyRepository;
use App\services\ContextImprovementService;
use App\utils\Audit;
use App\utils\Logger;
use App\utils\Request;
use App\utils\Response;
use App\utils\Validator;

final class CompanyController {
    private const TONES=['Profissional','Amigável','Premium','Persuasivo','Casual','Formal'];
    private const IMPROVABLE_FIELDS=[
        'segment'=>'Segmento da empresa',
        'description'=>'Descrição da empresa',
        'business_hours'=>'Horário de funcionamento',
        'products'=>'Produtos e soluções',
        'target_audience'=>'Público-alvo',
        'differentials'=>'Diferenciais',
        'avoid_words'=>'Palavras e abordagens a evitar',
        'preferred_words'=>'Palavras e abordagens preferidas',
        'specific_rules'=>'Regras específicas de comunicação',
        'additional_info'=>'Informações adicionais da empresa',
    ];

    public function show(array $u):array{return ['company'=>(new CompanyRepository())->find((int)$u['company_id'])];}

    public function update(array $u):array{
        $d=Request::json();
        $allowed=['name','segment','description','phone','whatsapp','email','address','website','instagram','business_hours','products','target_audience','differentials','communication_tone','avoid_words','preferred_words','specific_rules','additional_info','onboarding_step'];
        $before=(new CompanyRepository())->find((int)$u['company_id']);
        $clean=[];
        foreach($allowed as $k){
            if(!array_key_exists($k,$d))continue;
            if($k==='onboarding_step'){$clean[$k]=max(1,min(3,(int)$d[$k]));continue;}
            $max=in_array($k,['description','business_hours','products','target_audience','differentials','avoid_words','preferred_words','specific_rules','additional_info'],true)?10000:500;
            $clean[$k]=Validator::clean($d[$k],$max);
        }
        if(isset($clean['name'])&&$clean['name']!==''&&!Validator::companyName($clean['name']))Response::json(['error'=>'Informe um nome de empresa válido.'],422);
        if(isset($clean['email'])&&$clean['email']!==''&&!Validator::email($clean['email']))Response::json(['error'=>'Informe um e-mail comercial válido.'],422);
        if(isset($clean['communication_tone'])&&!Validator::oneOf($clean['communication_tone'],self::TONES))Response::json(['error'=>'Tom de comunicação inválido.'],422);
        foreach(['phone','whatsapp'] as $phoneField){if(isset($clean[$phoneField])&&$clean[$phoneField]!==''&&strlen((string)preg_replace('/\D/','',$clean[$phoneField]))<8)Response::json(['error'=>'Informe telefone e WhatsApp com número válido.'],422);}
        if(isset($clean['website'])&&$clean['website']!==''&&!preg_match('#^(https?://)?([a-z0-9-]+\.)+[a-z]{2,}(/.*)?$#i',$clean['website']))Response::json(['error'=>'Informe um site válido.'],422);
        if(isset($clean['instagram'])&&$clean['instagram']!==''&&!preg_match('#^(@?[a-z0-9._]{2,30}|https?://(www\.)?instagram\.com/.+)$#i',$clean['instagram']))Response::json(['error'=>'Informe @usuario ou uma URL válida do Instagram.'],422);

        $set=[];$vals=[];
        foreach($clean as $k=>$v){$set[]="$k=?";$vals[]=$v;}
        if($set){$vals[]=$u['company_id'];Database::get()->prepare('UPDATE companies SET '.implode(',',$set).',updated_at=NOW() WHERE id=?')->execute($vals);}
        $after=(new CompanyRepository())->find((int)$u['company_id']);
        Audit::record((int)$u['id'],'update','company',(int)$u['company_id'],$before,$after);
        return ['message'=>'Empresa atualizada.'];
    }

    public function improveContext(array $u):array{
        $d=Request::json();
        $field=Validator::clean($d['field']??'',60);
        $value=Validator::clean($d['value']??'',2200);
        if(!isset(self::IMPROVABLE_FIELDS[$field]))Response::json(['error'=>'Este campo não permite refinamento automático.'],422);
        if(Validator::length($value)<3)Response::json(['error'=>'Escreva ao menos uma informação inicial antes de pedir a melhoria.'],422);
        if(env('OPENAI_API_KEY','')==='')Response::json(['error'=>'O assistente de contexto ainda não foi configurado pelo administrador.'],503);
        $draft=[];
        if(isset($d['draft_context'])&&is_array($d['draft_context'])){
            foreach(['name','segment','description','address','business_hours','products','target_audience','differentials','communication_tone','avoid_words','preferred_words','specific_rules','additional_info'] as $key){
                if(array_key_exists($key,$d['draft_context']))$draft[$key]=Validator::clean($d['draft_context'][$key],2200);
            }
        }
        try{
            return (new ContextImprovementService())->improve($u,$field,$value,self::IMPROVABLE_FIELDS[$field],$draft);
        }catch(\Throwable $e){
            Logger::write('errors','context_improvement_failed',['user_id'=>(int)$u['id'],'company_id'=>(int)$u['company_id'],'field'=>$field,'message'=>$e->getMessage()]);
            Response::json(['error'=>'Não foi possível melhorar este campo com segurança agora. Tente adicionar mais um detalhe e repita.'],502);
        }
    }

    public function services(array $u):array{return ['services'=>(new CompanyRepository())->services((int)$u['company_id'])];}
    public function createService(array $u):never{$d=Request::json();$name=mb_substr(trim((string)($d['name']??'')),0,150);if($name==='')Response::json(['error'=>'Informe o nome do serviço.'],422);$pdo=Database::get();$pdo->prepare('INSERT INTO company_services(company_id,name,description,price,duration,notes,active,created_at,updated_at) VALUES(?,?,?,?,?,?,1,NOW(),NOW())')->execute([$u['company_id'],$name,mb_substr((string)($d['description']??''),0,5000),($d['price']??'')===''?null:(float)$d['price'],mb_substr((string)($d['duration']??''),0,100),mb_substr((string)($d['notes']??''),0,5000)]);$id=(int)$pdo->lastInsertId();Audit::record((int)$u['id'],'create','company_service',$id,null,$d);Response::json(['id'=>$id],201);}
    public function updateService(array $u,int $id):array{$d=Request::json();$s=Database::get()->prepare('SELECT * FROM company_services WHERE id=? AND company_id=?');$s->execute([$id,$u['company_id']]);$before=$s->fetch();if(!$before)Response::json(['error'=>'Serviço não encontrado.'],404);Database::get()->prepare('UPDATE company_services SET name=?,description=?,price=?,duration=?,notes=?,active=?,updated_at=NOW() WHERE id=? AND company_id=?')->execute([mb_substr((string)($d['name']??$before['name']),0,150),mb_substr((string)($d['description']??$before['description']),0,5000),array_key_exists('price',$d)?(($d['price']==='')?null:(float)$d['price']):$before['price'],mb_substr((string)($d['duration']??$before['duration']),0,100),mb_substr((string)($d['notes']??$before['notes']),0,5000),array_key_exists('active',$d)?(int)!empty($d['active']):(int)$before['active'],$id,$u['company_id']]);Audit::record((int)$u['id'],'update','company_service',$id,$before,$d);return ['message'=>'Serviço atualizado.'];}
    public function deleteService(array $u,int $id):array{Database::get()->prepare('DELETE FROM company_services WHERE id=? AND company_id=?')->execute([$id,$u['company_id']]);Audit::record((int)$u['id'],'delete','company_service',$id);return ['message'=>'Serviço excluído.'];}
}
