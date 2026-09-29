<?php
namespace App\middleware;
use App\config\Database;
use App\utils\Request;
use App\utils\Response;
final class AuthMiddleware {
    public static function user(bool $required=true): ?array {
        $token=Request::sessionToken(); if(!$token){if($required)Response::json(['error'=>'Não autenticado.'],401);return null;}
        $st=Database::get()->prepare("SELECT u.id,u.company_id,u.name,u.email,u.role,u.status,u.email_verified_at,s.id session_id,s.csrf_token_hash,s.expires_at FROM auth_sessions s JOIN users u ON u.id=s.user_id WHERE s.token_hash=? AND s.expires_at>NOW() LIMIT 1");
        $st->execute([hash('sha256',$token)]);$u=$st->fetch();
        if(!$u||$u['status']!=='active'||empty($u['email_verified_at'])){if($required)Response::json(['error'=>'Sessão inválida ou usuário bloqueado.'],401);return null;}
        Database::get()->prepare('UPDATE auth_sessions SET last_used_at=NOW() WHERE id=?')->execute([$u['session_id']]);
        return $u;
    }
    public static function admin(): array {$u=self::user(true);if(($u['role']??'')!=='admin')Response::json(['error'=>'Acesso restrito ao administrador.'],403);return $u;}
}
