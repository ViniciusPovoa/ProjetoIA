<?php
namespace App\repositories;
use App\config\Database;
final class HistoryRepository {
    public function page(int $userId,int $page,int $perPage,?int $agentId=null,bool $favorites=false):array{$pdo=Database::get();$where='ar.user_id=?';$args=[$userId];if($agentId){$where.=' AND ar.agent_id=?';$args[]=$agentId;}if($favorites)$where.=' AND ar.favorite=1';$count=$pdo->prepare("SELECT COUNT(*) FROM agent_runs ar WHERE $where");$count->execute($args);$off=($page-1)*$perPage;$sql="SELECT ar.id,a.id agent_id,a.name agent_name,a.slug agent_slug,ar.input_json,ar.output_text,ar.credits_used,ar.input_tokens,ar.output_tokens,ar.estimated_cost_brl,ar.duration_ms,ar.status,ar.created_at,ar.favorite,m.model_name,p.name provider_name FROM agent_runs ar JOIN agents a ON a.id=ar.agent_id JOIN ai_models m ON m.id=ar.model_id JOIN ai_providers p ON p.id=ar.provider_id WHERE $where ORDER BY ar.id DESC LIMIT $perPage OFFSET $off";$st=$pdo->prepare($sql);$st->execute($args);return ['items'=>$st->fetchAll(),'total'=>(int)$count->fetchColumn()];}
}
