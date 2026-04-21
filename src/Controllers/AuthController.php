<?php
declare(strict_types=1);

namespace Art\SelecaoNextSi\Controllers;

use Art\SelecaoNextSi\Services\AuthService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Throwable;
use OpenApi\Attributes as OA;

class AuthController
{
    private AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    #[OA\Post(
        path: "/auth/login",
        summary: "Autenticação de Usuário",
        tags: ["Autenticação"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["email", "password"],
                properties: [
                    new OA\Property(property: "email", type: "string", format: "email", example: "admin@nextsi.com.br"),
                    new OA\Property(property: "password", type: "string", example: "Admin@123")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200, 
                description: "Login bem-sucedido, retorna o JWT",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "token", type: "string", example: "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...")
                    ]
                )
            ),
            new OA\Response(response: 400, description: "Payload inválido ou não formatado como JSON"),
            new OA\Response(response: 401, description: "Credenciais inválidas"),
            new OA\Response(response: 422, description: "E-mail ou senha ausentes ou com formato inválido"),
            new OA\Response(response: 500, description: "Erro interno do servidor")
        ]
    )]
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