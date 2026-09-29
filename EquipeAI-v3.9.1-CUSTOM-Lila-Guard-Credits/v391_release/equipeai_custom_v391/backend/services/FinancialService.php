<?php
namespace App\services;
use App\config\Database;
final class FinancialService {
    public function summary(?string $month=null):array{$pdo=Database::get();$month=$month&&preg_match('/^\d{4}-\d{2}$/',$month)?$month:date('Y-m');$start=$month.'-01 00:00:00';$end=date('Y-m-d H:i:s',strtotime($start.' +1 month'));
        $q=function(string $sql,array $a=[])use($pdo){$s=$pdo->prepare($sql);$s->execute($a);return (float)$s->fetchColumn();};
        $revenue=$q("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='approved' AND paid_at>=? AND paid_at<?",[$start,$end]);$ai=$q('SELECT COALESCE(SUM(estimated_cost_brl),0) FROM ai_usage_logs WHERE created_at>=? AND created_at<?',[$start,$end]);$infra=$q("SELECT COALESCE(SUM(amount_brl),0) FROM system_costs WHERE category='infrastructure' AND reference_month=?",[$month.'-01']);$other=$q("SELECT COALESCE(SUM(amount_brl),0) FROM system_costs WHERE category='other' AND reference_month=?",[$month.'-01']);$margin=$revenue-$ai-$infra-$other;
        return ['month'=>$month,'revenue'=>round($revenue,2),'ai_cost'=>round($ai,2),'infrastructure_cost'=>round($infra,2),'other_cost'=>round($other,2),'margin'=>round($margin,2),'margin_percent'=>$revenue>0?round(($margin/$revenue)*100,1):0];}
}
