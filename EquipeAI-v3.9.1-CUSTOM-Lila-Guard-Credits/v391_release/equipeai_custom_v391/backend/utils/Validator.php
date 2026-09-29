<?php
namespace App\utils;

final class Validator {
    public static function clean(mixed $value, int $max=255): string {
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string)$value) ?? '';
        return self::slice(trim($value), 0, $max);
    }

    public static function email(string $value): bool {
        if ($value === '' || strlen($value) > 190 || str_contains($value, "\n") || str_contains($value, "\r")) return false;
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function fullName(string $value): bool {
        $value = trim($value);
        if (self::length($value) < 3 || self::length($value) > 120) return false;
        return (bool)preg_match("/^[\\p{L}][\\p{L}\\p{M} .'-]{1,118}[\\p{L}.]$/u", $value);
    }

    public static function companyName(string $value): bool {
        $value = trim($value);
        if (self::length($value) < 2 || self::length($value) > 150) return false;
        return !preg_match('/[<>\\x00-\\x1F]/u', $value);
    }

    public static function password(string $value): bool { return self::passwordErrors($value) === []; }

    public static function passwordErrors(string $value): array {
        $errors=[];
        if (strlen($value) < 10) $errors[]='Use pelo menos 10 caracteres.';
        if (strlen($value) > 128) $errors[]='A senha deve ter no máximo 128 caracteres.';
        if (!preg_match('/[a-z]/', $value)) $errors[]='Inclua uma letra minúscula.';
        if (!preg_match('/[A-Z]/', $value)) $errors[]='Inclua uma letra maiúscula.';
        if (!preg_match('/\d/', $value)) $errors[]='Inclua um número.';
        if (!preg_match('/[^A-Za-z0-9]/', $value)) $errors[]='Inclua um símbolo.';
        $compact=strtolower(preg_replace('/\s+/', '', $value) ?? '');
        if (in_array($compact,['password123!','senha123456!','123456789a!','qwerty123!','admin12345!'],true)) $errors[]='Escolha uma senha menos previsível.';
        return $errors;
    }

    public static function oneOf(mixed $value, array $allowed): bool { return in_array($value, $allowed, true); }
    public static function positiveInt(mixed $value): ?int { $v=filter_var($value,FILTER_VALIDATE_INT); return ($v!==false && $v>0)?$v:null; }
    public static function length(string $value): int { return function_exists('mb_strlen') ? mb_strlen($value,'UTF-8') : strlen($value); }
    public static function slice(string $value,int $start,int $length): string { return function_exists('mb_substr') ? mb_substr($value,$start,$length,'UTF-8') : substr($value,$start,$length); }
}
