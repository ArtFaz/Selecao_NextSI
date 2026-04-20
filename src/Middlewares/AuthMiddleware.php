<?php
declare(strict_types=1);

namespace Art\SelecaoNextSi\Middlewares;

use Art\SelecaoNextSi\Services\AuthService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Nyholm\Psr7\Response;
use Exception;

class AuthMiddleware
{
    private AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    public function __invoke(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $header = $request->getHeaderLine('Authorization');

        if ($header === '' || !preg_match('/^\s*Bearer\s+(\S+)\s*$/i', $header, $matches)) {
            return $this->jsonResponse(['error' => 'Token de autenticação ausente ou mal formatado.'], 401);
        }

        $tokenString = $matches[1];

        try {
            $decodedToken = $this->authService->validateToken($tokenString);
        } catch (Exception $e) {
            return $this->jsonResponse(['error' => $e->getMessage()], 401);
        }

        $request = $request->withAttribute('user_id', $decodedToken->sub);
        $request = $request->withAttribute('user_profile', $decodedToken->profile);

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