<?php
declare(strict_types=1);

namespace Art\SelecaoNextSi\Controllers;

use Art\SelecaoNextSi\Services\UserService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Throwable;

class UserController
{
    private UserService $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

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