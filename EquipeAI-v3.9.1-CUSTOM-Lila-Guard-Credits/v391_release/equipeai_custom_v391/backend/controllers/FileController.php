<?php
namespace App\controllers;
use App\config\Database;
use App\services\FileService;
use App\utils\Response;
final class FileController {
    public function upload(array $u):never{if(!isset($_FILES['file']))Response::json(['error'=>'Arquivo ausente.'],422);Response::json((new FileService())->upload($u,$_FILES['file']),201);}
    public function index(array $u):array{$s=Database::get()->prepare('SELECT id,original_name,extension,mime_type,size_bytes,IF(LENGTH(COALESCE(extracted_text,\'\'))>0,1,0) extracted,created_at FROM uploaded_files WHERE user_id=? AND company_id=? ORDER BY id DESC');$s->execute([$u['id'],$u['company_id']]);return ['files'=>$s->fetchAll()];}
    public function content(array $u,int $id):array{$s=Database::get()->prepare('SELECT id,original_name,extension,size_bytes,extracted_text FROM uploaded_files WHERE id=? AND user_id=? AND company_id=?');$s->execute([$id,$u['id'],$u['company_id']]);$f=$s->fetch();if(!$f)Response::json(['error'=>'Arquivo não encontrado.'],404);return ['file'=>$f];}
    public function download(array $u,int $id):never{$f=(new FileService())->forUser($id,$u);$path=(string)$f['storage_path'];if(!is_file($path))Response::json(['error'=>'Arquivo físico não encontrado.'],404);header('Content-Type: '.($f['mime_type']?:'application/octet-stream'));header('Content-Length: '.filesize($path));header("Content-Disposition: attachment; filename*=UTF-8''".rawurlencode((string)$f['original_name']));header('X-Content-Type-Options: nosniff');readfile($path);exit;}
    public function delete(array $u,int $id):array{(new FileService())->delete($id,$u);return ['message'=>'Arquivo excluído.'];}
}
