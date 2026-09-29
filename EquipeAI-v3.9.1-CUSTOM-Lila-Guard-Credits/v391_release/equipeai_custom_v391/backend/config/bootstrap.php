<?php
declare(strict_types=1);

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) return;
    $relative = substr($class, strlen($prefix));
    $file = dirname(__DIR__) . '/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) require_once $file;
});

function env(string $key, ?string $default = null): ?string {
    static $vars = null;
    if ($vars === null) {
        $vars = [];
        $file = dirname(__DIR__) . '/.env';
        if (is_file($file)) {
            foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
                [$k, $v] = explode('=', $line, 2);
                $vars[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
            }
        }
    }
    return $_ENV[$key] ?? $vars[$key] ?? $default;
}

function env_bool(string $key, bool $default = false): bool {
    $value = env($key, $default ? 'true' : 'false');
    return filter_var($value, FILTER_VALIDATE_BOOLEAN);
}

date_default_timezone_set(env('APP_TIMEZONE', 'America/Sao_Paulo') ?: 'America/Sao_Paulo');
if (env('APP_ENV', 'production') === 'production' && !env_bool('APP_DEBUG')) ini_set('display_errors', '0');

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
header('X-Permitted-Cross-Domain-Policies: none');
header('Cross-Origin-Opener-Policy: same-origin');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
if ((($_SERVER['HTTPS'] ?? '') === 'on')) header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
header("Content-Security-Policy: default-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self' https://www.mercadopago.com https://*.mercadopago.com;");
