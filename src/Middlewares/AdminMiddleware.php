<?php
declare(strict_types=1);

namespace Art\SelecaoNextSi\Middlewares;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Nyholm\Psr7\Response;

class AdminMiddleware
{
    public function __invoke(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $profile = $request->getAttribute('user_profile');

        if ($profile !== 'admin') {
            return $this->jsonResponse(['error' => 'Acesso negado. Apenas administradores podem realizar esta ação.'], 403);
        }

        return $handler->handle($request);
    }

    private function jsonResponse(array $payload, int $status): ResponseInterface
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            $json = '{"error":"Falha ao gerar resposta JSON."}';
            $status = 500;
        }

        $response = new Response($status);
        $response->getBody()->write($json);
        return $response->withHeader('Content-Type', 'application/json');
    }
}