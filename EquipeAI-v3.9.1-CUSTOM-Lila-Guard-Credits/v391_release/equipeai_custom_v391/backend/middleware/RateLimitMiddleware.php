<?php
namespace App\middleware;
use App\config\Database;
use App\utils\Request;
use App\utils\Response;
final class RateLimitMiddleware {
    public static function check(string $bucket,int $limit,int $seconds,?int $userId=null): void {
        $raw=$bucket.'|'.Request::ip().'|'.($userId??'guest');$key=hash('sha256',$raw);$pdo=Database::get();$pdo->beginTransaction();
        try{$st=$pdo->prepare('SELECT hits,expires_at FROM rate_limits WHERE identifier=? FOR UPDATE');$st->execute([$key]);$r=$st->fetch();$now=time();
            if(!$r||strtotime($r['expires_at'])<=$now){$pdo->prepare('REPLACE INTO rate_limits(identifier,hits,expires_at) VALUES(?,1,DATE_ADD(NOW(),INTERVAL ? SECOND))')->execute([$key,$seconds]);$pdo->commit();return;}
            if((int)$r['hits']>=$limit){$pdo->commit();Response::json(['error'=>'Muitas tentativas. Aguarde alguns minutos e tente novamente.'],429);}
            $pdo->prepare('UPDATE rate_limits SET hits=hits+1 WHERE identifier=?')->execute([$key]);$pdo->commit();
        }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }
}
