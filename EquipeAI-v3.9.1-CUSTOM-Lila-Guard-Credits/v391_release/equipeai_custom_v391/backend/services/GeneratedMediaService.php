<?php
namespace App\services;

use App\config\Database;
use RuntimeException;

final class GeneratedMediaService {
    public function persist(int $companyId,string $dataUri,string $mime): array {
        if(!preg_match('#^data:([^;]+);base64,(.+)$#s',$dataUri,$m))throw new RuntimeException('Imagem gerada inválida.');
        $detected=strtolower(trim($m[1]?:$mime));$binary=base64_decode($m[2],true);
        if($binary===false||strlen($binary)<100)throw new RuntimeException('Não foi possível preparar a imagem para publicação.');

        $finalMime=$detected;$finalBinary=$binary;$ext='bin';$publishable=false;
        if($detected==='image/jpeg'||$detected==='image/jpg'){$finalMime='image/jpeg';$ext='jpg';$publishable=true;}
        elseif($detected==='image/png'){
            $ext='png';
            if(function_exists('imagecreatefromstring')){$img=@imagecreatefromstring($binary);if($img!==false){ob_start();imagejpeg($img,null,92);$jpeg=ob_get_clean();imagedestroy($img);if(is_string($jpeg)&&$jpeg!==''){$finalBinary=$jpeg;$finalMime='image/jpeg';$ext='jpg';$publishable=true;}}}
        }elseif($detected==='image/svg+xml'){
            $ext='svg';
            if(class_exists('Imagick')){
                try{$im=new \Imagick();$im->setBackgroundColor('white');$im->readImageBlob($binary);$im->setImageFormat('jpeg');$im->setImageCompressionQuality(92);$im->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE);$jpeg=$im->getImagesBlob();$im->clear();$im->destroy();if(is_string($jpeg)&&strlen($jpeg)>100){$finalBinary=$jpeg;$finalMime='image/jpeg';$ext='jpg';$publishable=true;}}catch(\Throwable){}
            }
        }
        if($ext==='bin')throw new RuntimeException('Formato de imagem não suportado para mídia pública.');

        $token=bin2hex(random_bytes(24));
        $dir=dirname(__DIR__).'/storage/generated-media/'.$companyId;
        if(!is_dir($dir)&&!mkdir($dir,0750,true)&&!is_dir($dir))throw new RuntimeException('Não foi possível criar o diretório de mídia.');
        $file=$token.'.'.$ext;$path=$dir.'/'.$file;
        if(file_put_contents($path,$finalBinary,LOCK_EX)===false)throw new RuntimeException('Não foi possível salvar a imagem gerada.');
        $relative='generated-media/'.$companyId.'/'.$file;
        $pdo=Database::get();
        $pdo->prepare('INSERT INTO generated_media_assets(company_id,token,storage_path,mime,expires_at,created_at) VALUES(?,?,?,?,DATE_ADD(NOW(),INTERVAL 48 HOUR),NOW())')->execute([$companyId,$token,$relative,$finalMime]);
        $id=(int)$pdo->lastInsertId();
        $base=rtrim((string)env('APP_URL',''),'/');
        if($base==='')throw new RuntimeException('APP_URL precisa estar configurada para publicação social.');
        return ['id'=>$id,'token'=>$token,'mime'=>$finalMime,'public_url'=>$base.'/api/public-media/'.$token,'expires_in_hours'=>48,'publishable'=>$publishable];
    }

    public function stream(string $token): never {
        if(!preg_match('/^[a-f0-9]{48}$/',$token)){$this->notFound();}
        $s=Database::get()->prepare('SELECT storage_path,mime,expires_at FROM generated_media_assets WHERE token=? LIMIT 1');$s->execute([$token]);$row=$s->fetch();
        if(!$row||strtotime((string)$row['expires_at'])<time())$this->notFound();
        $path=dirname(__DIR__).'/storage/'.ltrim((string)$row['storage_path'],'/');
        if(!is_file($path))$this->notFound();
        header('Content-Type: '.((string)$row['mime']?:'application/octet-stream'));
        header('Content-Length: '.filesize($path));
        header('Cache-Control: public, max-age=3600');
        header('X-Content-Type-Options: nosniff');
        readfile($path);exit;
    }

    private function notFound(): never {http_response_code(404);header('Content-Type: text/plain; charset=utf-8');echo 'Mídia não encontrada.';exit;}
}
