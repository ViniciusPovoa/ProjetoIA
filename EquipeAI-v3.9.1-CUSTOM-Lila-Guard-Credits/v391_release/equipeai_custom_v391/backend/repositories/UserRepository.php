<?php
namespace App\repositories;
use App\config\Database;
final class UserRepository {
    public function findByEmail(string $email):?array{$s=Database::get()->prepare('SELECT * FROM users WHERE email=? LIMIT 1');$s->execute([$email]);return $s->fetch()?:null;}
    public function find(int $id):?array{$s=Database::get()->prepare('SELECT id,company_id,name,email,role,status,email_verified_at,last_login_at,created_at FROM users WHERE id=?');$s->execute([$id]);return $s->fetch()?:null;}
    public function list(int $page,int $perPage,string $search=''):array{$pdo=Database::get();$off=($page-1)*$perPage;$where='';$args=[];if($search!==''){$where='WHERE u.name LIKE ? OR u.email LIKE ? OR c.name LIKE ?';$q='%'.$search.'%';$args=[$q,$q,$q];}$count=$pdo->prepare("SELECT COUNT(*) FROM users u LEFT JOIN companies c ON c.id=u.company_id $where");$count->execute($args);$sql="SELECT u.id,u.name,u.email,u.role,u.status,u.email_verified_at,u.last_login_at,u.created_at,c.name company,s.plan_id,p.name plan_name,s.status subscription_status FROM users u LEFT JOIN companies c ON c.id=u.company_id LEFT JOIN subscriptions s ON s.user_id=u.id LEFT JOIN plans p ON p.id=s.plan_id $where ORDER BY u.id DESC LIMIT $perPage OFFSET $off";$st=$pdo->prepare($sql);$st->execute($args);return ['items'=>$st->fetchAll(),'total'=>(int)$count->fetchColumn()];}
}
