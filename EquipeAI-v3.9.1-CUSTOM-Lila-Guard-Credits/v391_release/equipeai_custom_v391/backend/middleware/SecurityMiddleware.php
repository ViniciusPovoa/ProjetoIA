<?php
namespace App\middleware;
use App\config\Database;
use App\utils\Request;
final class SecurityMiddleware {
    public static function cors(): void {
        $origin=Request::origin();$allowed=rtrim(env('FRONTEND_URL',env('APP_URL',''))??'','/');
        if($origin && $allowed && hash_equals($allowed,$origin)){header('Access-Control-Allow-Origin: '.$origin);header('Access-Control-Allow-Credentials: true');header('Vary: Origin');}
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');
        header('Access-Control-Allow-Methods: GET,POST,PUT,PATCH,DELETE,OPTIONS');
    }
    public static function log(string $event,?int $userId=null,array $details=[]): void {
        try{Database::get()->prepare('INSERT INTO security_logs(user_id,event,ip,user_agent,endpoint,details,created_at) VALUES(?,?,?,?,?,?,NOW())')->execute([$userId,$event,Request::ip(),Request::userAgent(),parse_url($_SERVER['REQUEST_URI']??'',PHP_URL_PATH),$details?json_encode($details,JSON_UNESCAPED_UNICODE):null]);}catch(\Throwable){}
    }
}
