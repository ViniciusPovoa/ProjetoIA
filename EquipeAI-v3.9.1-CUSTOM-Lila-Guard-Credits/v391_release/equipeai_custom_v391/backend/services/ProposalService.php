<?php
namespace App\services;

use App\config\Database;
use App\repositories\CompanyRepository;
use App\utils\Validator;
use RuntimeException;

final class ProposalService {
    public function templates():array{return (new ProposalPdfService())->templates();}

    public function create(array $user,array $input):array{
        $template=Validator::clean((string)($input['template']??'executive'),60);
        $title=Validator::clean((string)($input['title']??'Proposta Comercial'),190);
        $client=Validator::clean((string)($input['client_name']??''),190);
        $service=Validator::clean((string)($input['service']??''),300);
        $value=Validator::clean((string)($input['value']??''),150);
        $deadline=Validator::clean((string)($input['deadline']??''),150);
        $content=Validator::clean((string)($input['content']??''),20000);
        $runId=(int)($input['run_id']??0);
        if(trim($content)==='')throw new RuntimeException('A proposta ainda não possui conteúdo para gerar o PDF.');
        $company=(new CompanyRepository())->find((int)$user['company_id'])?:[];
        $binary=(new ProposalPdfService())->render($company,[
            'title'=>$title,'client_name'=>$client,'service'=>$service,'value'=>$value,'deadline'=>$deadline,'content'=>$content,
        ],$template);
        $token=bin2hex(random_bytes(24));
        $dir=dirname(__DIR__).'/storage/proposals/'.(int)$user['company_id'];
        if(!is_dir($dir)&&!mkdir($dir,0750,true)&&!is_dir($dir))throw new RuntimeException('Não foi possível criar a pasta de propostas.');
        $safeBase=$this->slug(($client!==''?$client.'-':'').($service!==''?$service:'proposta'));
        $filename='proposta-'.$safeBase.'-'.date('Ymd-His').'.pdf';
        $relative='proposals/'.(int)$user['company_id'].'/'.$token.'.pdf';
        $path=dirname(__DIR__).'/storage/'.$relative;
        if(file_put_contents($path,$binary,LOCK_EX)===false)throw new RuntimeException('Não foi possível salvar o PDF da proposta.');
        $pdo=Database::get();
        $stmt=$pdo->prepare('INSERT INTO proposal_documents(company_id,user_id,agent_run_id,template_slug,title,client_name,filename,storage_path,public_token,expires_at,created_at) VALUES(?,?,?,?,?,?,?,?,?,DATE_ADD(NOW(),INTERVAL 30 DAY),NOW())');
        $stmt->execute([(int)$user['company_id'],(int)$user['id'],$runId>0?$runId:null,$template,$title,$client?:null,$filename,$relative,$token]);
        $id=(int)$pdo->lastInsertId();
        $base=rtrim((string)env('APP_URL',''),'/');
        if($base==='')throw new RuntimeException('APP_URL precisa estar configurada para gerar o link da proposta.');
        return ['proposal'=>[
            'id'=>$id,'template'=>$template,'title'=>$title,'client_name'=>$client,'filename'=>$filename,
            'public_url'=>$base.'/api/public-proposals/'.$token,
            'download_url'=>$base.'/api/public-proposals/'.$token.'?download=1',
            'expires_in_days'=>30,
        ],'whatsapp_configured'=>(new WhatsAppService())->configured((int)$user['company_id'])];
    }

    public function findForCompany(int $id,int $companyId):array{
        $stmt=Database::get()->prepare('SELECT * FROM proposal_documents WHERE id=? AND company_id=? LIMIT 1');$stmt->execute([$id,$companyId]);$row=$stmt->fetch();if(!$row)throw new RuntimeException('Proposta não encontrada.');return $row;
    }

    public function publicByToken(string $token):array{
        if(!preg_match('/^[a-f0-9]{48}$/',$token))throw new RuntimeException('Proposta inválida.');
        $stmt=Database::get()->prepare('SELECT * FROM proposal_documents WHERE public_token=? LIMIT 1');$stmt->execute([$token]);$row=$stmt->fetch();if(!$row||strtotime((string)$row['expires_at'])<time())throw new RuntimeException('Esta proposta expirou ou não está disponível.');return $row;
    }

    public function absolutePath(array $proposal):string{return dirname(__DIR__).'/storage/'.ltrim((string)$proposal['storage_path'],'/');}

    public function logDelivery(array $user,int $proposalId,string $channel,string $recipient,string $status,?string $providerMessageId=null,?string $error=null):void{
        Database::get()->prepare('INSERT INTO proposal_deliveries(proposal_id,company_id,user_id,channel,recipient,status,provider_message_id,error_message,created_at) VALUES(?,?,?,?,?,?,?,?,NOW())')->execute([$proposalId,(int)$user['company_id'],(int)$user['id'],$channel,$recipient,$status,$providerMessageId,$error?substr($error,0,500):null]);
    }

    private function slug(string $text):string{
        $t=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$text);if($t===false)$t=$text;$t=strtolower($t);$t=preg_replace('/[^a-z0-9]+/','-',$t)??'proposta';$t=trim($t,'-');return substr($t?:'proposta',0,80);
    }
}
