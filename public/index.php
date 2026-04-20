<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use Slim\Factory\AppFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Art\SelecaoNextSi\Config\Database;

$rootPath = dirname(__DIR__);
if (file_exists($rootPath . '/.env')) {
    Dotenv::createImmutable($rootPath)->safeLoad();
}

$app = AppFactory::create();

// Middleware de erro (false em prod)
$app->addErrorMiddleware(true, true, true);

/**
 * Healthcheck
 * Endpoint básico para monitoramento e validação de disponibilidade da API e do Banco.
 */
$app->get('/ping', function (Request $request, Response $response): Response {
    $dbStatus = 'offline';
    
    try {
        $db = Database::getConnection();
        $dbStatus = 'API e banco de dados funcionais';
    } catch (\Exception $e) {
        $dbStatus = 'API funcionando, mas conexão ao banco falhou';
    }

    $payload = json_encode([
        'api_status' => 'online',
        'db_status'  => $dbStatus,
        'timestamp'  => date('c')
    ]);
    
    $response->getBody()->write($payload);
    
    return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(200);
});

$app->run();