<?php
namespace App\services;

use App\config\Database;
use App\providers\HttpClient;
use App\utils\SecretBox;
use RuntimeException;

final class WhatsAppIntegrationService {
    private function appId():string{return trim((string)env('META_WHATSAPP_APP_ID',''));}
    private function appSecret():string{return trim((string)env('META_WHATSAPP_APP_SECRET',''));}
    private function configId():string{return trim((string)env('META_WHATSAPP_CONFIG_ID',''));}
    private function version():string{return trim((string)env('META_WHATSAPP_GRAPH_VERSION','v26.0'));}
    private function featureType():string{return trim((string)env('META_WHATSAPP_EMBEDDED_FEATURE_TYPE',''));}
    private function embeddedSignupVersion():string{return trim((string)env('META_WHATSAPP_EMBEDDED_SIGNUP_VERSION','v4'));}
    private function graphBase():string{return 'https://graph.facebook.com/'.$this->version();}

    public function configured():bool{return $this->appId()!==''&&$this->appSecret()!==''&&$this->configId()!=='';}

    public function status(int $companyId):array{
        $row=null;
        try{
            $s=Database::get()->prepare('SELECT waba_id,phone_number_id,display_phone_number,verified_name,quality_rating,registration_status,last_send_at,created_at,updated_at FROM whatsapp_integrations WHERE company_id=? LIMIT 1');
            $s->execute([$companyId]);$row=$s->fetch()?:null;
        }catch(\Throwable){}
        return [
            'configured'=>$this->configured(),
            'connected'=>(bool)$row,
            'account'=>$row,
            'sdk'=>[
                'app_id'=>$this->configured()?$this->appId():'',
                'config_id'=>$this->configured()?$this->configId():'',
                'graph_version'=>$this->version(),
                'feature_type'=>$this->featureType(),
                'embedded_signup_version'=>$this->embeddedSignupVersion(),
            ],
        ];
    }

    public function complete(array $user,array $input):array{
        if(!$this->configured())throw new RuntimeException('WhatsApp Embedded Signup ainda não foi configurado pelo administrador.');
        $code=trim((string)($input['code']??''));
        if($code==='')throw new RuntimeException('A Meta não retornou o código de autorização do WhatsApp. Tente conectar novamente.');
        $wabaId=$this->digits((string)($input['waba_id']??''));
        $phoneNumberId=$this->digits((string)($input['phone_number_id']??''));
        $businessId=$this->digits((string)($input['business_id']??''));

        $tokenData=$this->exchangeCode($code);
        $token=(string)($tokenData['access_token']??'');
        if($token==='')throw new RuntimeException('Não foi possível obter a credencial do WhatsApp Business.');

        if($wabaId==='')$wabaId=$this->discoverWabaId($token);
        if($wabaId==='')throw new RuntimeException('Não foi possível identificar a conta do WhatsApp Business selecionada.');

        $phones=$this->phoneNumbers($wabaId,$token);
        if($phoneNumberId===''){
            if(count($phones)===1)$phoneNumberId=(string)($phones[0]['id']??'');
            elseif(count($phones)>1)throw new RuntimeException('A conta possui mais de um número. Conclua novamente o fluxo escolhendo o número que deseja conectar.');
        }
        if($phoneNumberId==='')throw new RuntimeException('Não foi possível identificar o número de WhatsApp selecionado.');

        $phoneInfo=[];
        foreach($phones as $phone){if((string)($phone['id']??'')===$phoneNumberId){$phoneInfo=$phone;break;}}
        if(!$phoneInfo){
            try{$phoneInfo=$this->graphGet('/'.rawurlencode($phoneNumberId),$token,['fields'=>'display_phone_number,verified_name,quality_rating']);}catch(\Throwable){}
        }

        try{$this->graphPost('/'.rawurlencode($wabaId).'/subscribed_apps',$token,[]);}catch(\Throwable){}

        $expiresIn=(int)($tokenData['expires_in']??0);
        $expiresAt=$expiresIn>0?date('Y-m-d H:i:s',time()+$expiresIn):null;
        $pdo=Database::get();
        $registrationStatus=$this->featureType()==='whatsapp_business_app_onboarding'?'registered':'connected';
        $sql="INSERT INTO whatsapp_integrations(company_id,meta_business_id,waba_id,phone_number_id,display_phone_number,verified_name,quality_rating,access_token_enc,token_type,token_expires_at,registration_status,connected_by_user_id,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE meta_business_id=VALUES(meta_business_id),waba_id=VALUES(waba_id),phone_number_id=VALUES(phone_number_id),display_phone_number=VALUES(display_phone_number),verified_name=VALUES(verified_name),quality_rating=VALUES(quality_rating),access_token_enc=VALUES(access_token_enc),token_type=VALUES(token_type),token_expires_at=VALUES(token_expires_at),registration_status=VALUES(registration_status),connected_by_user_id=VALUES(connected_by_user_id),updated_at=NOW()";
        $pdo->prepare($sql)->execute([
            (int)$user['company_id'],$businessId?:null,$wabaId,$phoneNumberId,
            (string)($phoneInfo['display_phone_number']??''),(string)($phoneInfo['verified_name']??''),(string)($phoneInfo['quality_rating']??''),
            SecretBox::encrypt($token),(string)($tokenData['token_type']??'bearer'),$expiresAt,$registrationStatus,(int)$user['id']
        ]);
        return $this->status((int)$user['company_id']);
    }

    public function register(array $user,string $pin):array{
        if($this->featureType()==='whatsapp_business_app_onboarding')throw new RuntimeException('Este numero usa coexistencia e ja foi registrado pelo WhatsApp Business App.');
        $pin=preg_replace('/\D+/','',$pin)??'';
        if(!preg_match('/^\d{6}$/',$pin))throw new RuntimeException('Informe um PIN de 6 dígitos para a verificação em duas etapas do WhatsApp.');
        $integration=$this->integration((int)$user['company_id']);$token=$this->token($integration);
        $response=$this->graphPost('/'.rawurlencode((string)$integration['phone_number_id']).'/register',$token,['messaging_product'=>'whatsapp','pin'=>$pin]);
        if(empty($response['success']))throw new RuntimeException('A Meta não confirmou o registro deste número.');
        Database::get()->prepare("UPDATE whatsapp_integrations SET registration_status='registered',updated_at=NOW() WHERE company_id=?")->execute([(int)$user['company_id']]);
        return $this->status((int)$user['company_id']);
    }

    public function disconnect(int $companyId):void{Database::get()->prepare('DELETE FROM whatsapp_integrations WHERE company_id=?')->execute([$companyId]);}

    public function integration(int $companyId):array{
        $s=Database::get()->prepare('SELECT * FROM whatsapp_integrations WHERE company_id=? LIMIT 1');$s->execute([$companyId]);$row=$s->fetch();
        if(!$row)throw new RuntimeException('Conecte o WhatsApp Business da empresa antes de enviar a proposta.');
        return $row;
    }

    public function token(array $integration):string{
        $expiresAt=strtotime((string)($integration['token_expires_at']??''));
        if($expiresAt&&$expiresAt<=time())throw new RuntimeException('A conexão com o WhatsApp expirou. Conecte a conta novamente.');
        return SecretBox::decrypt((string)$integration['access_token_enc']);
    }

    private function exchangeCode(string $code):array{
        $params=['client_id'=>$this->appId(),'client_secret'=>$this->appSecret(),'code'=>$code];
        return HttpClient::get($this->graphBase().'/oauth/access_token?'.http_build_query($params));
    }

    private function discoverWabaId(string $businessToken):string{
        try{
            $appToken=$this->appId().'|'.$this->appSecret();
            $debug=HttpClient::get($this->graphBase().'/debug_token?'.http_build_query(['input_token'=>$businessToken,'access_token'=>$appToken]));
            $scopes=$debug['data']['granular_scopes']??[];
            foreach(is_array($scopes)?$scopes:[] as $scope){
                $name=(string)($scope['scope']??'');
                if(in_array($name,['whatsapp_business_management','whatsapp_business_messaging'],true)){
                    foreach(($scope['target_ids']??[]) as $target){$id=$this->digits((string)$target);if($id!=='')return $id;}
                }
            }
        }catch(\Throwable){}
        return '';
    }

    private function phoneNumbers(string $wabaId,string $token):array{
        $data=$this->graphGet('/'.rawurlencode($wabaId).'/phone_numbers',$token,['fields'=>'id,display_phone_number,verified_name,quality_rating']);
        return is_array($data['data']??null)?$data['data']:[];
    }
    private function graphGet(string $path,string $token,array $params=[]):array{$url=$this->graphBase().$path;if($params)$url.='?'.http_build_query($params);return HttpClient::get($url,['Authorization: Bearer '.$token]);}
    private function graphPost(string $path,string $token,array $payload):array{return HttpClient::post($this->graphBase().$path,['Authorization: Bearer '.$token],$payload);}
    private function digits(string $value):string{return preg_replace('/\D+/','',$value)??'';}
}
