<?php
namespace App\controllers;

use App\services\GeneratedMediaService;
use App\services\InstagramService;
use App\utils\Request;

final class InstagramController {
    public function status(array $u):array{return (new InstagramService())->status((int)$u['company_id']);}
    public function connect(array $u):array{$d=Request::json();return ['url'=>(new InstagramService())->connectUrl($u,(string)($d['return_to']??'/app'))];}
    public function callback():never{
        $code=(string)($_GET['code']??'');$state=(string)($_GET['state']??'');$error=(string)($_GET['error']??'');
        $front=rtrim((string)env('FRONTEND_URL',''),'/');
        try{
            if($error!=='')throw new \RuntimeException('Autorização cancelada no Instagram.');
            $return=(new InstagramService())->handleCallback($code,$state);
            $this->finishPopup($front,$return,true,'Instagram conectado com sucesso.');
        }catch(\Throwable $e){$this->finishPopup($front,'/app',false,mb_substr($e->getMessage(),0,180));}
    }
    public function disconnect(array $u):array{(new InstagramService())->disconnect((int)$u['company_id']);return ['message'=>'Instagram desconectado.'];}
    public function contentPublishingLimit(array $u):array{return (new InstagramService())->contentPublishingLimit((int)$u['company_id']);}
    public function publish(array $u):array{return (new InstagramService())->publish($u,Request::json());}
    public function testMedia(array $u):array{
        $format=(string)(Request::json()['format']??'publication');
        $format=$format==='story'?'story':'publication';
        $dimensions=$format==='story'?'1080/1920':'1080/1350';
        $seed=bin2hex(random_bytes(10));
        $ch=curl_init('https://picsum.photos/seed/'.$seed.'/'.$dimensions.'.jpg');
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_MAXREDIRS=>3,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>30,CURLOPT_USERAGENT=>'EquipeAI/3.9.1']);
        $binary=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$mime=strtolower((string)curl_getinfo($ch,CURLINFO_CONTENT_TYPE));$curlError=curl_error($ch);curl_close($ch);
        if(!is_string($binary)||$curlError!==''||$status<200||$status>=300||strlen($binary)<1000||strlen($binary)>12*1024*1024||!str_starts_with($mime,'image/jpeg'))throw new \RuntimeException('Nao foi possivel preparar a foto de teste agora.');
        $asset=(new GeneratedMediaService())->persist((int)$u['company_id'],'data:image/jpeg;base64,'.base64_encode($binary),'image/jpeg');
        return ['image'=>$asset['public_url'],'format'=>$format,'credits_used'=>0,'provider'=>'test-media','model'=>'picsum','mime'=>$asset['mime'],'media_url'=>$asset['public_url'],'media_asset_id'=>$asset['id'],'media_expires_in_hours'=>$asset['expires_in_hours'],'media_publishable'=>$asset['publishable']];
    }

    private function finishPopup(string $front,string $return,bool $ok,string $message):never{
        $target=$front.$return.(str_contains($return,'?')?'&':'?').'instagram='.($ok?'connected':'error');
        $payload=json_encode(['type'=>'equipeai:instagram-oauth','ok'=>$ok,'message'=>$message],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $targetJs=json_encode($target,JSON_UNESCAPED_SLASHES);$origin=parse_url($front,PHP_URL_SCHEME).'://'.parse_url($front,PHP_URL_HOST);$port=parse_url($front,PHP_URL_PORT);if($port)$origin.=':'.$port;$originJs=json_encode($origin,JSON_UNESCAPED_SLASHES);
        header('Content-Type: text/html; charset=utf-8');
        header("Content-Security-Policy: default-src 'none'; script-src 'unsafe-inline'; style-src 'unsafe-inline'; base-uri 'none'; frame-ancestors 'none'");
        echo '<!doctype html><html lang="pt-BR"><meta charset="utf-8"><title>Instagram</title><style>body{font-family:system-ui;background:#f6f7f9;color:#172033;display:grid;place-items:center;min-height:100vh;margin:0}.box{background:#fff;border:1px solid #e1e6ed;border-radius:16px;padding:28px;max-width:420px;text-align:center}p{color:#657287}</style><body><div class="box"><h2>'.($ok?'Instagram conectado':'Não foi possível conectar').'</h2><p>'.htmlspecialchars($message,ENT_QUOTES,'UTF-8').'</p></div><script>(function(){var payload='.$payload.',target='.$targetJs.',origin='.$originJs.';if(window.opener&&!window.opener.closed){window.opener.postMessage(payload,origin);setTimeout(function(){window.close()},250)}else{window.location.replace(target)}})();</script></body></html>';exit;
    }
}
