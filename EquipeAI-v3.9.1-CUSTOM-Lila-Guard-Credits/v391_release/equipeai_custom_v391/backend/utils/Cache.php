<?php
namespace App\utils;

final class Cache
{
    public static function remember(string $key, int $ttlSeconds, callable $resolver): mixed
    {
        $dir = dirname(__DIR__) . '/storage/cache';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $file = $dir . '/' . hash('sha256', $key) . '.cache.php';
        if (is_file($file) && (filemtime($file) + $ttlSeconds) >= time()) {
            $payload = @include $file;
            if (is_array($payload) && array_key_exists('value', $payload)) {
                return $payload['value'];
            }
        }

        $value = $resolver();
        $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
        $content = "<?php\nreturn " . var_export(['value' => $value], true) . ";\n";
        if (@file_put_contents($tmp, $content, LOCK_EX) !== false) {
            @chmod($tmp, 0640);
            @rename($tmp, $file);
        }
        return $value;
    }

    public static function clear(): void
    {
        $dir = dirname(__DIR__) . '/storage/cache';
        foreach (glob($dir . '/*.cache.php') ?: [] as $file) {
            @unlink($file);
        }
    }
}
