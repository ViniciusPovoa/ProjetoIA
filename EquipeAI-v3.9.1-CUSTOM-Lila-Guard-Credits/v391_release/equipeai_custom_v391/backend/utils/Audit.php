<?php
namespace App\utils;
use App\config\Database;
final class Audit {
    public static function record(?int $actor, string $action, string $entityType, string|int|null $entityId=null, ?array $before=null, ?array $after=null): void {
        try { Database::get()->prepare('INSERT INTO audit_logs(actor_user_id,action,entity_type,entity_id,before_json,after_json,ip,created_at) VALUES(?,?,?,?,?,?,?,NOW())')->execute([$actor,$action,$entityType,$entityId===null?null:(string)$entityId,$before?json_encode($before,JSON_UNESCAPED_UNICODE):null,$after?json_encode($after,JSON_UNESCAPED_UNICODE):null,Request::ip()]); } catch (\Throwable) {}
    }
}
