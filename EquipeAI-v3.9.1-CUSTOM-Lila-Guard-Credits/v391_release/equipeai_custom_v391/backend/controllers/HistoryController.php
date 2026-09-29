<?php
namespace App\controllers;
use App\config\Database;
use App\repositories\HistoryRepository;
use App\utils\Request;
use App\utils\Response;
final class HistoryController {
    public function index(array $u):array{$page=Request::queryInt('page',1,1,100000);$per=Request::queryInt('per_page',20,5,100);$agent=isset($_GET['agent_id'])?(int)$_GET['agent_id']:null;$fav=($_GET['favorites']??'')==='1';$r=(new HistoryRepository())->page((int)$u['id'],$page,$per,$agent,$fav);foreach($r['items'] as &$i)$i['input']=json_decode((string)$i['input_json'],true);return ['history'=>$r['items'],'pagination'=>['page'=>$page,'per_page'=>$per,'total'=>$r['total'],'pages'=>(int)ceil($r['total']/$per)]];}
    public function show(array $u,int $id):array{$s=Database::get()->prepare('SELECT ar.*,a.name agent_name,a.slug agent_slug,m.model_name,p.name provider_name FROM agent_runs ar JOIN agents a ON a.id=ar.agent_id JOIN ai_models m ON m.id=ar.model_id JOIN ai_providers p ON p.id=ar.provider_id WHERE ar.id=? AND ar.user_id=?');$s->execute([$id,$u['id']]);$i=$s->fetch();if(!$i)Response::json(['error'=>'Histórico não encontrado.'],404);$i['input']=json_decode((string)$i['input_json'],true);return ['item'=>$i];}
    public function favorite(array $u,int $id):array{$d=Request::json();Database::get()->prepare('UPDATE agent_runs SET favorite=? WHERE id=? AND user_id=?')->execute([(int)!empty($d['favorite']),$id,$u['id']]);return ['message'=>'Histórico atualizado.'];}
    public function delete(array $u,int $id):array{Database::get()->prepare('DELETE FROM agent_runs WHERE id=? AND user_id=?')->execute([$id,$u['id']]);return ['message'=>'Item excluído.'];}
}
