<?php
namespace App\repositories;
use App\config\Database;
final class AgentRepository {
    public function active():array{return Database::get()->query("SELECT id,name,slug,description,icon,category,input_schema,credit_cost FROM agents WHERE active=1 ORDER BY sort_order,id")->fetchAll();}
    public function findActive(int $id):?array{$s=Database::get()->prepare("SELECT a.*,p.slug provider_slug,p.active provider_active,m.model_name,m.active model_active,m.input_cost_per_million,m.output_cost_per_million,m.cost_currency FROM agents a JOIN ai_providers p ON p.id=a.provider_id JOIN ai_models m ON m.id=a.model_id WHERE a.id=? AND a.active=1 LIMIT 1");$s->execute([$id]);return $s->fetch()?:null;}
}
