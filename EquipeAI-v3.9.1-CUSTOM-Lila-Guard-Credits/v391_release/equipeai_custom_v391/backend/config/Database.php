<?php
namespace App\config;
use PDO;
use PDOException;
use App\utils\SystemException;
final class Database {
    private static ?PDO $pdo = null;
    public static function get(): PDO {
        if (self::$pdo) return self::$pdo;
        $host = env('DB_HOST', 'localhost');
        $port = env('DB_PORT', '3306');
        $name = env('DB_NAME', '');
        $options=[
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        if (defined('PDO::MYSQL_ATTR_MULTI_STATEMENTS')) $options[constant('PDO::MYSQL_ATTR_MULTI_STATEMENTS')]=false;
        try {
            self::$pdo = new PDO("mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4", env('DB_USER',''), env('DB_PASSWORD',''), $options);
            return self::$pdo;
        } catch (PDOException $e) {
            throw new SystemException('Falha ao conectar ao banco de dados.');
        }
    }
}
