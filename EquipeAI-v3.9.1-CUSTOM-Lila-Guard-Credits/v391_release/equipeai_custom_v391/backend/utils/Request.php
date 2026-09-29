<?php
namespace App\utils;
final class Request {
    public static function json(): array {
        $raw = file_get_contents('php://input');
        if (!$raw) return [];
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }
    public static function bearer(): ?string {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/^Bearer\s+(.+)$/i', $header, $m)) return trim($m[1]);
        return null;
    }
    public static function sessionToken(): ?string { return self::bearer() ?: ($_COOKIE['saas_session'] ?? null); }
    public static function csrf(): ?string { return $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null; }
    public static function ip(): string {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }
    public static function userAgent(): string { return mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255); }
    public static function origin(): string { return rtrim($_SERVER['HTTP_ORIGIN'] ?? '', '/'); }
    public static function queryInt(string $key, int $default, int $min=1, int $max=100): int {
        $v = filter_input(INPUT_GET, $key, FILTER_VALIDATE_INT);
        if ($v === false || $v === null) return $default;
        return max($min, min($max, $v));
    }
}
