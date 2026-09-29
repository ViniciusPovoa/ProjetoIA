<?php
namespace App\middleware;
use App\utils\Request;
use App\utils\Response;
final class CsrfMiddleware {
    public static function verify(array $user): void {
        if(Request::bearer()) return;
        $token=Request::csrf();
        if(!$token || !hash_equals((string)$user['csrf_token_hash'],hash('sha256',$token))) Response::json(['error'=>'Token CSRF inválido. Atualize a página e tente novamente.'],419);
    }
}
