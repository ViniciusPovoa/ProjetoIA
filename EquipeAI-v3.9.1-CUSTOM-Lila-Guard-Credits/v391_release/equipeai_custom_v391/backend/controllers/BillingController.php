<?php
namespace App\controllers;
use App\config\Database;
use App\services\MercadoPagoService;
use App\utils\Request;
use App\utils\Cache;
final class BillingController {
    public function credits(array $u):array{$s=Database::get()->prepare('SELECT balance FROM user_credits WHERE user_id=?');$s->execute([$u['id']]);return ['balance'=>(int)$s->fetchColumn()];}
    public function transactions(array $u):array{$s=Database::get()->prepare('SELECT id,type,amount,balance_after,description,status,created_at FROM credit_transactions WHERE user_id=? ORDER BY id DESC LIMIT 200');$s->execute([$u['id']]);return ['transactions'=>$s->fetchAll()];}
    public function plans():array{return Cache::remember('active_plans_v2',60,function(){ $rows=Database::get()->query('SELECT id,name,slug,description,price,credits,duration_days,features,limits_json FROM plans WHERE active=1 ORDER BY sort_order,price')->fetchAll();foreach($rows as &$r){$r['features']=json_decode((string)$r['features'],true)?:[];$r['limits']=json_decode((string)$r['limits_json'],true)?:[];}return ['plans'=>$rows];});}
    public function status(array $u):array{$s=Database::get()->prepare('SELECT s.*,p.name plan_name,p.price,p.credits,p.features FROM subscriptions s JOIN plans p ON p.id=s.plan_id WHERE s.user_id=? LIMIT 1');$s->execute([$u['id']]);$sub=$s->fetch()?:null;if($sub)$sub['features']=json_decode((string)$sub['features'],true)?:[];return ['subscription'=>$sub];}
    public function create(array $u):array{$d=Request::json();return (new MercadoPagoService())->createSubscription($u,(int)($d['plan_id']??0));}
    public function cancel(array $u):array{return (new MercadoPagoService())->cancel($u);}
    public function webhook():array{(new MercadoPagoService())->webhook(Request::json(),$_GET);return ['received'=>true];}
}
