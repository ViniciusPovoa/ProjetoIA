<?php
namespace App\services;
final class CostService {
    public function estimateBrl(array $model,int $inputTokens,int $outputTokens):float{$in=(float)($model['input_cost_per_million']??0);$out=(float)($model['output_cost_per_million']??0);$cost=($inputTokens/1000000)*$in+($outputTokens/1000000)*$out;if(($model['cost_currency']??'USD')==='USD')$cost*=max(.01,(float)env('USD_BRL_RATE','5.50'));return round($cost,6);}
}
