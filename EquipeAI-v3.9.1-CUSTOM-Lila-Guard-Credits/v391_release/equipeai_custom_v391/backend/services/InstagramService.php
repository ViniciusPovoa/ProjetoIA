<?php
namespace App\services;

use App\config\Database;
use App\providers\HttpClient;
use App\utils\SecretBox;
use RuntimeException;

final class InstagramService {
    private function appId():string{return trim((string)env('META_INSTAGRAM_APP_ID',''));}
    private function appSecret():string{return trim((string)env('META_INSTAGRAM_APP_SECRET',''));}
    private function redirectUri():string{return trim((string)env('META_INSTAGRAM_REDIRECT_URI',rtrim((string)env('APP_URL',''),'/').'/api/integrations/instagram/callback'));}
    private function graphVersion():string{return trim((string)env('META_INSTAGRAM_GRAPH_VERSION','v26.0'));}
    private function graphBase():string{return 'https://graph.instagram.com/'.$this->graphVersion();}

    public function configured():bool{return $this->appId()!==''&&$this->appSecret()!==''&&$this->redirectUri()!=='';}

    public function status(int $companyId):array{
        $s=Database::get()->prepare('SELECT ig_user_id,username,account_type,token_expires_at,last_publish_at,created_at,updated_at FROM instagram_integrations WHERE company_id=? LIMIT 1');$s->execute([$companyId]);$row=$s->fetch();
        return ['configured'=>$this->configured(),'connected'=>(bool)$row,'account'=>$row?:null];
    }

    public function connectUrl(array $user,string $returnTo=''):string{
        if(!$this->configured())throw new RuntimeException('Integração do Instagram ainda não foi configurada pelo administrador.');
        $returnTo=$this->safeReturnTo($returnTo);
        $payload=['uid'=>(int)$user['id'],'cid'=>(int)$user['company_id'],'exp'=>time()+600,'nonce'=>bin2hex(random_bytes(10)),'return'=>$returnTo];
        $json=json_encode($payload,JSON_UNESCAPED_SLASHES);$body=$this->b64url($json?:'{}');$sig=hash_hmac('sha256',$body,$this->appSecret());$state=$body.'.'.$sig;
        $query=http_build_query([
            'client_id'=>$this->appId(),
            'redirect_uri'=>$this->redirectUri(),
            'scope'=>'instagram_business_basic,instagram_business_content_publish',
            'response_type'=>'code',
            'state'=>$state,
            'enable_fb_login'=>'0',
            'force_authentication'=>'1',
        ]);
        return 'https://www.instagram.com/oauth/authorize?'.$query;
    }

    public function handleCallback(string $code,string $state):string{
        if(!$this->configured())throw new RuntimeException('Integração do Instagram não configurada.');
        $payload=$this->verifyState($state);
        if($code==='')throw new RuntimeException('Código de autorização do Instagram ausente.');

        $short=$this->exchangeCode($code);
        $long=$this->exchangeLongLived((string)$short['access_token']);
        $token=(string)($long['access_token']??$short['access_token']);
        $expires=(int)($long['expires_in']??3600);
        $profile=$this->getProfile($token);
        $igId=(string)($profile['user_id']??$profile['id']??$short['user_id']??'');
        if($igId==='')throw new RuntimeException('Não foi possível identificar a conta profissional do Instagram.');
        $username=(string)($profile['username']??'');$accountType=(string)($profile['account_type']??'');
        $pdo=Database::get();
        $sql="INSERT INTO instagram_integrations(company_id,ig_user_id,username,account_type,access_token_enc,token_expires_at,scopes,connected_by_user_id,created_at,updated_at) VALUES(?,?,?,?,?,DATE_ADD(NOW(),INTERVAL ? SECOND),?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE ig_user_id=VALUES(ig_user_id),username=VALUES(username),account_type=VALUES(account_type),access_token_enc=VALUES(access_token_enc),token_expires_at=VALUES(token_expires_at),scopes=VALUES(scopes),connected_by_user_id=VALUES(connected_by_user_id),updated_at=NOW()";
        $pdo->prepare($sql)->execute([(int)$payload['cid'],$igId,$username,$accountType,SecretBox::encrypt($token),$expires,'instagram_business_basic,instagram_business_content_publish',(int)$payload['uid']]);
        return (string)($payload['return']??'/app');
    }

    public function disconnect(int $companyId):void{Database::get()->prepare('DELETE FROM instagram_integrations WHERE company_id=?')->execute([$companyId]);}

    public function contentPublishingLimit(int $companyId):array{
        $integration=$this->integration($companyId);
        $token=$this->usableToken($integration);
        $response=$this->graphGet('/'.rawurlencode((string)$integration['ig_user_id']).'/content_publishing_limit',$token,['fields'=>'quota_usage,config']);
        return ['called'=>true,'username'=>(string)$integration['username'],'permission'=>'instagram_business_content_publish','result'=>$response];
    }

    public function publish(array $user,array $input):array{
        $integration=$this->integration((int)$user['company_id']);
        $token=$this->usableToken($integration);
        $mediaUrl=trim((string)($input['media_url']??''));$caption=trim((string)($input['caption']??''));$kind=strtolower(trim((string)($input['kind']??'feed')));$alt=trim((string)($input['alt_text']??''));
        if(!filter_var($mediaUrl,FILTER_VALIDATE_URL)||!str_starts_with($mediaUrl,'https://'))throw new RuntimeException('A imagem precisa estar disponível por HTTPS para o Instagram.');
        $appHost=(string)parse_url((string)env('APP_URL',''),PHP_URL_HOST);$mediaHost=(string)parse_url($mediaUrl,PHP_URL_HOST);$mediaPath=(string)parse_url($mediaUrl,PHP_URL_PATH);if($appHost===''||strcasecmp($appHost,$mediaHost)!==0||!str_contains($mediaPath,'/api/public-media/'))throw new RuntimeException('Publique uma imagem gerada pelo EquipeAI. Gere ou regenere a peça antes de postar.');
        if(!in_array($kind,['feed','story'],true))throw new RuntimeException('Tipo de publicação inválido.');
        if(mb_strlen($caption)>2200)throw new RuntimeException('A legenda ultrapassa o limite de 2.200 caracteres do Instagram.');

        $payload=['image_url'=>$mediaUrl];
        if($kind==='story'){$payload['media_type']='STORIES';}
        else{if($caption!=='')$payload['caption']=$caption;if($alt!=='')$payload['alt_text']=mb_substr($alt,0,1000);}
        $container=$this->graphPost('/'.rawurlencode((string)$integration['ig_user_id']).'/media',$token,$payload);
        $containerId=(string)($container['id']??'');if($containerId==='')throw new RuntimeException('O Instagram não criou o contêiner de publicação.');

        $status='IN_PROGRESS';
        for($i=0;$i<5;$i++){
            usleep($i===0?300000:700000);
            $check=$this->graphGet('/'.rawurlencode($containerId),$token,['fields'=>'status_code,status']);
            $status=(string)($check['status_code']??'');
            if(in_array($status,['FINISHED','PUBLISHED'],true))break;
            if(in_array($status,['ERROR','EXPIRED'],true))throw new RuntimeException('O Instagram não conseguiu processar a imagem: '.mb_substr((string)($check['status']??$status),0,240));
        }
        $published=$this->graphPost('/'.rawurlencode((string)$integration['ig_user_id']).'/media_publish',$token,['creation_id'=>$containerId]);
        $mediaId=(string)($published['id']??'');if($mediaId==='')throw new RuntimeException('O Instagram não confirmou a publicação.');
        $permalink='';
        try{$media=$this->graphGet('/'.rawurlencode($mediaId),$token,['fields'=>'permalink']);$permalink=(string)($media['permalink']??'');}catch(\Throwable){}
        Database::get()->prepare('UPDATE instagram_integrations SET last_publish_at=NOW(),updated_at=NOW() WHERE company_id=?')->execute([(int)$user['company_id']]);
        Database::get()->prepare("INSERT INTO social_publish_logs(company_id,user_id,platform,ig_media_id,media_type,caption,permalink,status,published_at,created_at) VALUES(?,?,'instagram',?,?,?,?, 'published',NOW(),NOW())")->execute([(int)$user['company_id'],(int)$user['id'],$mediaId,$kind,$caption,$permalink]);
        return ['published'=>true,'media_id'=>$mediaId,'permalink'=>$permalink,'username'=>$integration['username'],'kind'=>$kind];
    }

    private function integration(int $companyId):array{
        $s=Database::get()->prepare('SELECT * FROM instagram_integrations WHERE company_id=? LIMIT 1');$s->execute([$companyId]);$row=$s->fetch();if(!$row)throw new RuntimeException('Conecte uma conta profissional do Instagram antes de publicar.');return $row;
    }

    private function usableToken(array $integration):string{
        $token=SecretBox::decrypt((string)$integration['access_token_enc']);
        $expiresAt=strtotime((string)($integration['token_expires_at']??''));
        if($expiresAt&&$expiresAt<time()+10*86400){
            try{$ref=$this->graphGetAbsolute('https://graph.instagram.com/refresh_access_token',['grant_type'=>'ig_refresh_token','access_token'=>$token]);if(!empty($ref['access_token'])){$token=(string)$ref['access_token'];$secs=(int)($ref['expires_in']??5184000);Database::get()->prepare('UPDATE instagram_integrations SET access_token_enc=?,token_expires_at=DATE_ADD(NOW(),INTERVAL ? SECOND),updated_at=NOW() WHERE id=?')->execute([SecretBox::encrypt($token),$secs,$integration['id']]);}}catch(\Throwable $e){if($expiresAt<time())throw new RuntimeException('A conexão com o Instagram expirou. Conecte a conta novamente.');}
        }
        if($expiresAt&&$expiresAt<time())throw new RuntimeException('A conexão com o Instagram expirou. Conecte a conta novamente.');
        return $token;
    }

    private function exchangeCode(string $code):array{
        $ch=curl_init('https://api.instagram.com/oauth/access_token');
        curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>12,CURLOPT_TIMEOUT=>45,CURLOPT_POSTFIELDS=>http_build_query(['client_id'=>$this->appId(),'client_secret'=>$this->appSecret(),'grant_type'=>'authorization_code','redirect_uri'=>$this->redirectUri(),'code'=>$code]),CURLOPT_HTTPHEADER=>['Content-Type: application/x-www-form-urlencoded']]);
        $raw=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);$data=json_decode((string)$raw,true);
        if($raw===false||$err!==''||$status<200||$status>=300||!is_array($data)||empty($data['access_token']))throw new RuntimeException('Falha ao concluir o login do Instagram. '.mb_substr((string)($data['error_message']??$data['error']['message']??''),0,180));
        return $data;
    }
    private function exchangeLongLived(string $short):array{return $this->graphGetAbsolute('https://graph.instagram.com/access_token',['grant_type'=>'ig_exchange_token','client_secret'=>$this->appSecret(),'access_token'=>$short]);}
    private function getProfile(string $token):array{return $this->graphGet('/me',$token,['fields'=>'user_id,username,account_type']);}
    private function graphGet(string $path,string $token,array $params=[]):array{$params['access_token']=$token;return $this->graphGetAbsolute($this->graphBase().$path,$params);}
    private function graphGetAbsolute(string $url,array $params):array{return HttpClient::get($url.'?'.http_build_query($params));}
    private function graphPost(string $path,string $token,array $payload):array{return HttpClient::post($this->graphBase().$path,['Authorization: Bearer '.$token],$payload);}

    private function verifyState(string $state):array{
        [$body,$sig]=array_pad(explode('.',$state,2),2,'');if($body===''||$sig===''||!hash_equals(hash_hmac('sha256',$body,$this->appSecret()),$sig))throw new RuntimeException('Estado OAuth inválido.');
        $json=base64_decode(strtr($body,'-_','+/'));$data=json_decode((string)$json,true);if(!is_array($data)||($data['exp']??0)<time())throw new RuntimeException('O login do Instagram expirou. Tente conectar novamente.');return $data;
    }
    private function b64url(string $s):string{return rtrim(strtr(base64_encode($s),'+/','-_'),'=');}
    private function safeReturnTo(string $path):string{$path=trim($path);return str_starts_with($path,'/app')?$path:'/app';}
}
