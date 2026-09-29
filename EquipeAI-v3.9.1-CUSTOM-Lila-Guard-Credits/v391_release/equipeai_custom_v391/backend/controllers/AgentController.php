<?php
namespace App\controllers;

use App\repositories\AgentRepository;
use App\services\AgentService;
use App\utils\Cache;
use App\utils\Request;
use App\utils\Response;

final class AgentController
{
    public function index(): array
    {
        return Cache::remember('active_agents_v2', 60, function (): array {
            $items = (new AgentRepository())->active();
            foreach ($items as &$item) {
                $item['input_schema'] = json_decode((string)$item['input_schema'], true);
            }
            return ['agents' => $items];
        });
    }

    public function show(int $id): array
    {
        $agent = (new AgentRepository())->findActive($id);
        if (!$agent) {
            Response::json(['error' => 'Agente não encontrado.'], 404);
        }
        unset(
            $agent['system_prompt'], $agent['provider_id'], $agent['model_id'],
            $agent['provider_active'], $agent['model_active'],
            $agent['input_cost_per_million'], $agent['output_cost_per_million'], $agent['cost_currency']
        );
        $agent['input_schema'] = json_decode((string)$agent['input_schema'], true);
        return ['agent' => $agent];
    }

    public function execute(array $user, int $id): array
    {
        return (new AgentService())->execute($user, $id, Request::json());
    }

    public function generateImage(array $user, int $id): array
    {
        return (new \App\services\ImageGenerationService())->generateMarketingImage($user, $id, Request::json());
    }
}
