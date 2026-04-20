<?php
declare(strict_types=1);

namespace Art\SelecaoNextSi\Config;

use PDO;
use PDOException;

class Database {
    // Armazena a instância única da conexão
    private static ?PDO $connection = null;

    public static function getConnection(): PDO {
        // Se a conexão já existir, devolve ela. Se não, cria uma nova
        if (self::$connection === null) {
            
            $host = $_ENV['DB_HOST'] ?? 'mysql';
            $port = $_ENV['DB_PORT'] ?? '3306';
            $db   = $_ENV['DB_DATABASE'] ?? 'nextsi_auth';
            $user = $_ENV['DB_USER'] ?? 'nextsi_user';
            $pass = $_ENV['DB_PASSWORD'] ?? 'nextsi_password';

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
                die(json_encode([
                    'error' => 'Falha crítica na conexão com o banco de dados.'
                ]));
            }
        }

        return self::$connection;
    }
}