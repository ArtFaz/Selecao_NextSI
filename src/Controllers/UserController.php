<?php
declare(strict_types=1);

namespace Art\SelecaoNextSi\Controllers;

use Art\SelecaoNextSi\Services\UserService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Throwable;
use OpenApi\Attributes as OA;

class UserController
{
    private UserService $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    #[OA\Get(
        path: "/users",
        summary: "Lista todos os usuários (Paginado)",
        security: [["bearerAuth" => []]],
        tags: ["Usuários"],
        parameters: [
            new OA\Parameter(name: "limit", in: "query", required: false, description: "Limite de resultados", schema: new OA\Schema(type: "integer", default: 50)),
            new OA\Parameter(name: "offset", in: "query", required: false, description: "Pular X resultados", schema: new OA\Schema(type: "integer", default: 0))
        ],
        responses: [
            new OA\Response(response: 200, description: "Lista de usuários retornada com sucesso"),
            new OA\Response(response: 401, description: "Não autorizado (Token ausente ou inválido)"),
            new OA\Response(response: 422, description: "Parâmetros de paginação inválidos")
        ]
    )]
    public function index(Request $request, Response $response): Response
    {
        try {
            $queryParams = $request->getQueryParams();

            $limit = 50;
            if (isset($queryParams['limit'])) {
                $rawLimit = $queryParams['limit'];
                if (!is_scalar($rawLimit) || filter_var((string) $rawLimit, FILTER_VALIDATE_INT) === false) {
                    return $this->jsonResponse($response, ['error' => 'Parâmetro limit inválido.'], 422);
                }
                $limit = (int) $rawLimit;
            }

            $offset = 0;
            if (isset($queryParams['offset'])) {
                $rawOffset = $queryParams['offset'];
                if (!is_scalar($rawOffset) || filter_var((string) $rawOffset, FILTER_VALIDATE_INT) === false) {
                    return $this->jsonResponse($response, ['error' => 'Parâmetro offset inválido.'], 422);
                }
                $offset = (int) $rawOffset;
            }

            $users = $this->userService->getAllUsers($limit, $offset);

            return $this->jsonResponse($response, ['data' => $users], 200);
        } catch (Throwable $e) {
            return $this->handleException($response, $e);
        }
    }

    #[OA\Get(
        path: "/users/{id}",
        summary: "Busca um usuário específico pelo ID",
        security: [["bearerAuth" => []]],
        tags: ["Usuários"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, description: "ID do usuário", schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Dados do usuário"),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 404, description: "Usuário não encontrado")
        ]
    )]
    public function show(Request $request, Response $response, array $args): Response
    {
        try {
            $id = (int) ($args['id'] ?? 0);
            $user = $this->userService->getUserById($id);

            return $this->jsonResponse($response, ['data' => $user], 200);
        } catch (Throwable $e) {
            return $this->handleException($response, $e);
        }
    }

    #[OA\Post(
        path: "/users",
        summary: "Cria um novo usuário (Apenas Admin)",
        security: [["bearerAuth" => []]],
        tags: ["Usuários"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["name", "email", "password", "document"],
                properties: [
                    new OA\Property(property: "name", type: "string", example: "Maria Teste"),
                    new OA\Property(property: "email", type: "string", format: "email", example: "maria@teste.com"),
                    new OA\Property(property: "password", type: "string", example: "senha_forte"),
                    new OA\Property(property: "document", type: "string", example: "01001205553"),
                    new OA\Property(property: "phone", type: "string", example: "14999999999"),
                    new OA\Property(property: "profile", type: "string", enum: ["admin", "user"], example: "user")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "Usuário criado com sucesso"),
            new OA\Response(response: 400, description: "Payload inválido ou não formatado como JSON"),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso negado (Não é admin)"),
            new OA\Response(response: 409, description: "E-mail ou Documento já existem"),
            new OA\Response(response: 422, description: "Erro de validação (campos obrigatórios, CPF/CNPJ inválido)")
        ]
    )]
    public function create(Request $request, Response $response): Response
    {
        try {
            $body = $request->getParsedBody();

            if (!is_array($body)) {
                return $this->jsonResponse($response, ['error' => 'Payload inválido ou não formatado como JSON.'], 400);
            }

            $newUserId = $this->userService->createUser($body);

            return $this->jsonResponse($response, [
                'message' => 'Usuário criado com sucesso.',
                'id' => $newUserId
            ], 201); // 201 Created
        } catch (Throwable $e) {
            return $this->handleException($response, $e);
        }
    }

    #[OA\Put(
        path: "/users/{id}",
        summary: "Atualiza um usuário existente (Apenas Admin)",
        security: [["bearerAuth" => []]],
        tags: ["Usuários"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "name", type: "string", example: "Maria Editada"),
                    new OA\Property(property: "email", type: "string", format: "email", example: "maria.nova@teste.com"),
                    new OA\Property(property: "password", type: "string", example: "nova_senha_forte"),
                    new OA\Property(property: "phone", type: "string", nullable: true, example: null),
                    new OA\Property(property: "profile", type: "string", enum: ["admin", "user"], example: "admin")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Usuário atualizado com sucesso"),
            new OA\Response(response: 400, description: "Payload inválido ou não formatado como JSON"),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso negado (Não é admin)"),
            new OA\Response(response: 404, description: "Usuário não encontrado"),
            new OA\Response(response: 409, description: "E-mail já está em uso por outra conta"),
            new OA\Response(response: 422, description: "Erro de validação nos campos enviados")
        ]
    )]
    public function update(Request $request, Response $response, array $args): Response
    {
        try {
            $id = (int) ($args['id'] ?? 0);
            $body = $request->getParsedBody();

            if (!is_array($body)) {
                return $this->jsonResponse($response, ['error' => 'Payload inválido ou não formatado como JSON.'], 400);
            }

            $this->userService->updateUser($id, $body);

            return $this->jsonResponse($response, ['message' => 'Usuário atualizado com sucesso.'], 200);
        } catch (Throwable $e) {
            return $this->handleException($response, $e);
        }
    }

    #[OA\Delete(
        path: "/users/{id}",
        summary: "Deleta um usuário (Apenas Admin)",
        security: [["bearerAuth" => []]],
        tags: ["Usuários"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Usuário deletado com sucesso"),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso negado (Não é admin)"),
            new OA\Response(response: 404, description: "Usuário não encontrado")
        ]
    )]
    public function delete(Request $request, Response $response, array $args): Response
    {
        try {
            $id = (int) ($args['id'] ?? 0);
            $this->userService->deleteUser($id);

            return $this->jsonResponse($response, ['message' => 'Usuário deletado com sucesso.'], 200);
        } catch (Throwable $e) {
            return $this->handleException($response, $e);
        }
    }

    /**
     * Centraliza o tratamento de exceções para garantir um fallback seguro de HTTP status.
     */
    private function handleException(Response $response, Throwable $e): Response
    {
        $status = $e->getCode();
        if ($status < 400 || $status > 599) {
            $status = 500; // Erros internos não mapeados ou falhas de sistema
        }

        return $this->jsonResponse($response, ['error' => $e->getMessage()], $status);
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