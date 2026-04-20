<?php
declare(strict_types=1);

namespace Art\SelecaoNextSi\Config;

use PDO;
use PDOException;
use RuntimeException;

class Database {
    // Armazena a instância única da conexão
    private static ?PDO $connection = null;

    public static function getConnection(): PDO {
        // Se a conexão já existir, devolve ela. Se não, cria uma nova
        if (self::$connection === null) {

            $host = self::env('DB_HOST', 'mysql');
            $port = self::env('DB_PORT', '3306');
            $db   = self::env('DB_DATABASE', 'nextsi_auth');
            $user = self::env('DB_USER', 'nextsi_user');
            $pass = self::env('DB_PASSWORD', 'nextsi_password');

            $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";

            try {
                self::$connection = new PDO($dsn, $user, $pass, [
                    // Lança exceções automáticas em caso de erro no SQL
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    // Retorna os dados do banco como Arrays Associativos (ex: $user['nome'])
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    // Desliga a emulação do PDO e usa a proteção real do MySQL contra SQL Injection
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
            } catch (PDOException $e) {
                throw new RuntimeException('Falha crítica na conexão com o banco de dados.', 0, $e);
            }
        }

        return self::$connection;
    }

    private static function env(string $key, string $default): string
    {
        if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
            return (string) $_ENV[$key];
        }

        if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
            return (string) $_SERVER[$key];
        }

        $value = getenv($key);
        if ($value !== false && $value !== '') {
            return (string) $value;
        }

        return $default;
    }
}