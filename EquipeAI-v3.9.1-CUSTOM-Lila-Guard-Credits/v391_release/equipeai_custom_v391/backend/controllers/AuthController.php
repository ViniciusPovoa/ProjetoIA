<?php
namespace App\controllers;
use App\middleware\AuthMiddleware;
use App\services\AuthService;
use App\utils\Request;
use App\utils\Response;

final class AuthController {
    public function register():never{Response::json((new AuthService())->register(Request::json()),201);}
    public function verify():array{$d=Request::json();return (new AuthService())->verifyEmail((string)($d['token']??''));}
    public function resend():array{$d=Request::json();return (new AuthService())->resendVerification((string)($d['email']??''));}
    public function login():array{$d=Request::json();return (new AuthService())->login((string)($d['email']??''),(string)($d['password']??''));}
    public function logout(array $user):array{$t=Request::sessionToken();if($t)(new AuthService())->logout($t);return ['message'=>'Sessão encerrada.'];}
    public function forgot():array{$d=Request::json();return (new AuthService())->forgot((string)($d['email']??''));}
    public function reset():array{$d=Request::json();(new AuthService())->reset((string)($d['token']??''),(string)($d['password']??''));return ['message'=>'Senha alterada. Faça login novamente.'];}
    public function change(array $user):array{$d=Request::json();(new AuthService())->changePassword((int)$user['id'],(string)($d['current_password']??''),(string)($d['new_password']??''));return ['message'=>'Senha alterada. Faça login novamente.'];}
    public function me():array{$u=AuthMiddleware::user(true);unset($u['csrf_token_hash'],$u['session_id'],$u['expires_at']);return ['user'=>$u];}
    public function csrf():array{$t=Request::sessionToken();if(!$t)Response::json(['error'=>'Não autenticado.'],401);return ['csrf_token'=>(new AuthService())->refreshCsrf($t)];}
    public function delete(array $user):array{(new AuthService())->deleteAccount((int)$user['id']);return ['message'=>'Conta e dados relacionados foram excluídos.'];}
}
