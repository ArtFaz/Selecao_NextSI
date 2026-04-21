<?php
declare(strict_types=1);

namespace Art\SelecaoNextSi\Controllers;

use Art\SelecaoNextSi\Services\AuthService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Throwable;

class AuthController
{
    private AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    public function login(Request $request, Response $response): Response
    {
        try {
            $body = $request->getParsedBody();

            if (!is_array($body)) {
                return $this->jsonResponse($response, ['error' => 'Payload inválido ou não formatado como JSON.'], 400);
            }

            $email = $body['email'] ?? '';
            $password = $body['password'] ?? '';

            if (!is_scalar($email) || !is_scalar($password)) {
                return $this->jsonResponse($response, ['error' => 'E-mail e senha devem ser valores textuais válidos.'], 422);
            }

            if (trim((string) $email) === '' || trim((string) $password) === '') {
                return $this->jsonResponse($response, ['error' => 'E-mail e senha são obrigatórios.'], 422);
            }

            // O AuthService fará a validação de banco, hash e geração do JWT
            $token = $this->authService->authenticate((string) $email, (string) $password);

            return $this->jsonResponse($response, ['token' => $token], 200);

        } catch (Throwable $e) {
            $status = $e->getCode();
            // Garante que o status code é um HTTP válido, fazendo fallback para 500 caso não seja
            if ($status < 400 || $status > 599) {
                $status = 500;
            }

            return $this->jsonResponse($response, ['error' => $e->getMessage()], $status);
        }
    }

    /**
     * Helper para padronizar as respostas JSON do controlador.
     */
    private function jsonResponse(Response $response, array $payload, int $status): Response
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            $json = '{"error":"Falha ao gerar resposta JSON."}';
            $status = 500;
        }

        $response->getBody()->write($json);
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}