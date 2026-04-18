<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

$app = AppFactory::create();

$app->addErrorMiddleware(true, true, true);

/**
 * Healthcheck
 * Endpoint básico para monitoramento e validação de disponibilidade da API.
 */
$app->get('/ping', function (Request $request, Response $response): Response {
    $payload = json_encode([
        'status' => 'online',
        'timestamp' => date('c') // Retorna no formato ISO 8601
    ]);
    
    $response->getBody()->write($payload);
    
    return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(200);
});

$app->run();