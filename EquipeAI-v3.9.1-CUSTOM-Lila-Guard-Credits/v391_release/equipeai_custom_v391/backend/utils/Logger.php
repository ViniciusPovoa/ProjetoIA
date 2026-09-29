<?php
namespace App\utils;
final class Logger {
    public static function write(string $channel, string $event, array $context=[]): void {
        $dir = dirname(__DIR__).'/storage/logs';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $safe = preg_replace('/[^a-z0-9_-]/i','_', $channel) ?: 'app';
        $context = self::redact($context);
        $row = ['time'=>date('c'),'event'=>$event,'context'=>$context];
        @file_put_contents($dir.'/'.$safe.'-'.date('Y-m-d').'.log', json_encode($row,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL, FILE_APPEND|LOCK_EX);
    }
    private static function redact(array $data): array {
        foreach ($data as $k=>$v) {
            if (preg_match('/password|token|secret|api[_-]?key|authorization/i',(string)$k)) {$data[$k]='[REDACTED]'; continue;}
            if (is_array($v)) $data[$k]=self::redact($v);
        }
        return $data;
    }
}
