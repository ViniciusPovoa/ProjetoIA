<?php
namespace App\utils;

use RuntimeException;

final class SecretBox {
    private static function key(): string {
        $configured = trim((string)env('APP_ENCRYPTION_KEY',''));
        if ($configured !== '') {
            if (preg_match('/^[a-f0-9]{64}$/i',$configured)) return hex2bin($configured);
            return hash('sha256',$configured,true);
        }
        $fallback = (string)env('META_INSTAGRAM_APP_SECRET',(string)env('META_WHATSAPP_APP_SECRET',''));
        if ($fallback === '') throw new RuntimeException('Configure APP_ENCRYPTION_KEY no .env para proteger tokens externos.');
        return hash('sha256',$fallback,true);
    }

    public static function encrypt(string $plain): string {
        $iv=random_bytes(12);$tag='';
        $cipher=openssl_encrypt($plain,'aes-256-gcm',self::key(),OPENSSL_RAW_DATA,$iv,$tag);
        if($cipher===false)throw new RuntimeException('Não foi possível proteger a credencial.');
        return base64_encode($iv.$tag.$cipher);
    }

    public static function decrypt(string $encoded): string {
        $raw=base64_decode($encoded,true);
        if($raw===false||strlen($raw)<29)throw new RuntimeException('Credencial protegida inválida.');
        $iv=substr($raw,0,12);$tag=substr($raw,12,16);$cipher=substr($raw,28);
        $plain=openssl_decrypt($cipher,'aes-256-gcm',self::key(),OPENSSL_RAW_DATA,$iv,$tag);
        if($plain===false)throw new RuntimeException('Não foi possível ler a credencial protegida.');
        return $plain;
    }
}
