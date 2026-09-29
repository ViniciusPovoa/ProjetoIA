<?php
namespace App\controllers;

use App\services\MailService;
use App\services\ProposalService;
use App\services\WhatsAppService;
use App\utils\Request;
use App\utils\Response;
use RuntimeException;

final class ProposalController {
    public function templates():array{return ['templates'=>(new ProposalService())->templates()];}
    public function create(array $user):array{return (new ProposalService())->create($user,Request::json());}

    public function email(array $user,int $id):array{
        $d=Request::json();$to=trim((string)($d['to']??''));if(!filter_var($to,FILTER_VALIDATE_EMAIL))Response::json(['error'=>'Informe um e-mail válido para o cliente.'],422);
        $subject=trim((string)($d['subject']??'Proposta comercial'));$message=trim((string)($d['message']??'Olá! Segue em anexo a proposta comercial para sua avaliação.'));
        $svc=new ProposalService();$proposal=$svc->findForCompany($id,(int)$user['company_id']);$path=$svc->absolutePath($proposal);if(!is_file($path))throw new RuntimeException('Arquivo da proposta não encontrado.');
        $ok=(new MailService())->sendWithAttachment($to,$subject,nl2br(htmlspecialchars($message,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')),$message,$path,(string)$proposal['filename'],'application/pdf');
        $svc->logDelivery($user,$id,'email',$to,$ok?'sent':'error',null,$ok?null:'Falha no envio SMTP');
        if(!$ok)throw new RuntimeException('Não foi possível enviar o e-mail. Revise a configuração SMTP.');
        return ['sent'=>true,'channel'=>'email'];
    }

    public function whatsapp(array $user,int $id):array{
        $d=Request::json();$phone=trim((string)($d['phone']??''));$caption=trim((string)($d['caption']??'Olá! Segue a proposta comercial em PDF para sua avaliação.'));
        $svc=new ProposalService();$proposal=$svc->findForCompany($id,(int)$user['company_id']);$base=rtrim((string)env('APP_URL',''),'/');$url=$base.'/api/public-proposals/'.(string)$proposal['public_token'];
        try{$result=(new WhatsAppService())->sendDocument((int)$user['company_id'],$phone,$url,(string)$proposal['filename'],$caption);$svc->logDelivery($user,$id,'whatsapp',$phone,'sent',$result['message_id']??null);return ['sent'=>true,'channel'=>'whatsapp','message_id'=>$result['message_id']??null,'mode'=>$result['mode']??'document'];}
        catch(\Throwable $e){$svc->logDelivery($user,$id,'whatsapp',$phone,'error',null,$e->getMessage());throw $e;}
    }
}
