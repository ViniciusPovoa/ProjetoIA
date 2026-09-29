<?php
namespace App\services;

use App\config\Database;
use App\providers\HttpClient;
use RuntimeException;

final class WhatsAppService {
    public function configured(int $companyId):bool{
        try{$s=Database::get()->prepare('SELECT 1 FROM whatsapp_integrations WHERE company_id=? LIMIT 1');$s->execute([$companyId]);return (bool)$s->fetchColumn();}catch(\Throwable){return false;}
    }

    public function sendDocument(int $companyId,string $phone,string $documentUrl,string $filename,string $caption=''):array{
        $integrationService=new WhatsAppIntegrationService();
        $integration=$integrationService->integration($companyId);
        $token=$integrationService->token($integration);
        $phone=preg_replace('/\D+/','',$phone)??'';
        if(strlen($phone)<10)throw new RuntimeException('Informe o WhatsApp do cliente com DDD e código do país.');
        $version=(string)env('META_WHATSAPP_GRAPH_VERSION',env('WHATSAPP_GRAPH_VERSION','v26.0'));
        $phoneId=(string)$integration['phone_number_id'];
        $url='https://graph.facebook.com/'.rawurlencode($version).'/'.rawurlencode($phoneId).'/messages';
        $payload=['messaging_product'=>'whatsapp','recipient_type'=>'individual','to'=>$phone,'type'=>'document','document'=>['link'=>$documentUrl,'filename'=>$filename]];
        if(trim($caption)!=='')$payload['document']['caption']=substr(trim($caption),0,1000);
        try{
            $response=HttpClient::post($url,['Authorization: Bearer '.$token],$payload);
            Database::get()->prepare('UPDATE whatsapp_integrations SET last_send_at=NOW(),updated_at=NOW() WHERE company_id=?')->execute([$companyId]);
            return ['message_id'=>(string)($response['messages'][0]['id']??''),'mode'=>'document','from'=>$integration['display_phone_number']??''];
        }catch(\Throwable $e){
            $template=(string)env('WHATSAPP_PROPOSAL_TEMPLATE_NAME','');
            if($template!==''){
                $templatePayload=['messaging_product'=>'whatsapp','to'=>$phone,'type'=>'template','template'=>['name'=>$template,'language'=>['code'=>(string)env('WHATSAPP_PROPOSAL_TEMPLATE_LANGUAGE','pt_BR')],'components'=>[['type'=>'header','parameters'=>[['type'=>'document','document'=>['link'=>$documentUrl,'filename'=>$filename]]]]]]];
                try{
                    $response=HttpClient::post($url,['Authorization: Bearer '.$token],$templatePayload);
                    Database::get()->prepare('UPDATE whatsapp_integrations SET last_send_at=NOW(),updated_at=NOW() WHERE company_id=?')->execute([$companyId]);
                    return ['message_id'=>(string)($response['messages'][0]['id']??''),'mode'=>'template','from'=>$integration['display_phone_number']??''];
                }catch(\Throwable){throw new RuntimeException('O WhatsApp da empresa recusou o envio. Fora da janela de atendimento, configure um template aprovado com cabeçalho de documento nessa mesma conta do WhatsApp Business.');}
            }
            throw new RuntimeException('O WhatsApp da empresa recusou o documento. Se o cliente não falou com esse número nas últimas 24h, é necessário um template aprovado para iniciar a conversa.');
        }
    }
}
