<?php
declare(strict_types=1);

namespace Art\SelecaoNextSi\Services;

use Art\SelecaoNextSi\Repositories\UserRepository;
use Exception;

class UserService
{
    private const ALLOWED_PROFILES = ['admin', 'user'];
    private const MAX_LIMIT = 100;

    private UserRepository $repository;

    public function __construct(UserRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Valida os dados e cria um novo usuário.
     * 
     * @throws Exception Se os dados forem inválidos ou já existirem.
     */
    public function createUser(array $data): int
    {
        // 1. Guarda de segurança: Validação de campos obrigatórios
        $requiredFields = ['name', 'email', 'password', 'document'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                throw new Exception("O campo obrigatório '{$field}' está ausente ou vazio.", 422); // 422 Unprocessable Entity
            }
        }

        $data['name'] = trim((string) $data['name']);
        $data['email'] = self::normalizeEmail((string) $data['email']);
        $data['password'] = (string) $data['password'];
        $data['phone'] = isset($data['phone']) ? trim((string) $data['phone']) : null;

        if ($data['name'] === '') {
            throw new Exception('O campo obrigatório name está ausente ou vazio.', 422);
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Formato de e-mail inválido.', 422);
        }

        if (!is_scalar($data['document'])) {
            throw new Exception('O campo document deve ser um valor textual válido.', 422);
        }
        $data['document'] = trim((string) $data['document']);
        if ($data['document'] === '') {
            throw new Exception('O campo obrigatório document está ausente ou vazio.', 422);
        }

        // 2. Validar e limpar o documento
        if (!DocumentValidator::isValid($data['document'])) {
            throw new Exception("O documento (CPF/CNPJ) fornecido é inválido.", 422);
        }
        $data['document'] = DocumentValidator::sanitize($data['document']);

        // 3. Verificar se o e-mail já existe
        if ($this->repository->findByEmail($data['email'])) {
            throw new Exception("Este e-mail já está em uso.", 409); // 409 Conflict
        }

        // 4. Verificar se o documento já existe
        if ($this->repository->findByDocument($data['document'])) {
            throw new Exception("Este documento já está cadastrado no sistema.", 409);
        }

        // 5. Garantir que o perfil seja estritamente 'admin' ou 'user'
        // Como apenas admins podem acessar essa rota, eles podem definir o perfil.
        if (isset($data['profile'])) {
            $data['profile'] = strtolower(trim((string) $data['profile']));
            if (!in_array($data['profile'], self::ALLOWED_PROFILES, true)) {
                throw new Exception("Perfil inválido. Deve ser 'admin' ou 'user'.", 422);
            }
        }
        $data['profile'] = $data['profile'] ?? 'user';

        if ($data['phone'] === '') {
            $data['phone'] = null;
        }

        // 6. Hash seguro da senha
        $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        if ($data['password'] === false) {
            throw new Exception('Falha ao processar a senha.', 500);
        }

        // 7. Enviar para persistência
        return $this->repository->create($data);
    }

    /**
     * Retorna todos os usuários com suporte a paginação.
     */
    public function getAllUsers(int $limit = 50, int $offset = 0): array
    {
        $limit = max(1, min($limit, self::MAX_LIMIT));
        $offset = max(0, $offset);

        return $this->repository->findAll($limit, $offset);
    }

    /**
     * Retorna um usuário específico pelo ID.
     */
    public function getUserById(int $id): array
    {
        $user = $this->repository->findById($id);

        if (!$user) {
            throw new Exception("Usuário não encontrado.", 404);
        }

        return $user;
    }

    /**
     * Atualiza os dados de um usuário existente.
     */
    public function updateUser(int $id, array $data): bool
    {
        $currentUser = $this->repository->findById($id);
        if (!$currentUser) {
            throw new Exception("Usuário não encontrado para atualização.", 404);
        }

        // Bloqueia tentativas de alteração do documento na rota de update
        if (array_key_exists('document', $data)) {
            unset($data['document']);
        }

        $allowedUpdateFields = ['name', 'email', 'phone', 'profile', 'password'];
        $data = array_intersect_key($data, array_flip($allowedUpdateFields));

        if ($data === []) {
            throw new Exception('Nenhum campo válido foi enviado para atualização.', 422);
        }

        if (array_key_exists('name', $data)) {
            $data['name'] = trim((string) $data['name']);
            if ($data['name'] === '') {
                throw new Exception('O campo name não pode ser vazio.', 422);
            }
        }

        if (array_key_exists('email', $data)) {
            $data['email'] = self::normalizeEmail((string) $data['email']);
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Formato de e-mail inválido.', 422);
            }
        }

        if (array_key_exists('phone', $data)) {
            $phone = trim((string) $data['phone']);
            $data['phone'] = $phone === '' ? null : $phone;
        }

        // Se estiver tentando alterar o e-mail, verifica se não vai colidir com outro usuário
        if (isset($data['email']) && $data['email'] !== $currentUser['email']) {
            if ($this->repository->findByEmail($data['email'])) {
                throw new Exception("Este e-mail já está em uso por outra conta.", 409);
            }
        }

        // Se o perfil for enviado, garante que é válido
        if (isset($data['profile'])) {
            $data['profile'] = strtolower(trim((string) $data['profile']));
            if (!in_array($data['profile'], self::ALLOWED_PROFILES, true)) {
                throw new Exception("Perfil inválido. Deve ser 'admin' ou 'user'.", 422);
            }
        }

        // Se a senha foi enviada na requisição, faz o hash
        if (array_key_exists('password', $data)) {
            if (trim((string) $data['password']) === '') {
                throw new Exception('O campo password não pode ser vazio.', 422);
            }
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
            if ($data['password'] === false) {
                throw new Exception('Falha ao processar a senha.', 500);
            }
        }

        // Preserva phone = null para permitir limpeza explícita desse campo.
        $sanitizedData = array_filter(
            $data,
            static fn($value, $key) => $value !== null || $key === 'phone',
            ARRAY_FILTER_USE_BOTH
        );

        unset($currentUser['created_at'], $currentUser['updated_at']);

        $updateData = array_merge(
            $currentUser,
            $sanitizedData
        );

        return $this->repository->update($id, $updateData);
    }

    /**
     * Deleta um usuário.
     */
    public function deleteUser(int $id): bool
    {
        $deleted = $this->repository->delete($id);

        if (!$deleted) {
            throw new Exception("Falha ao deletar: Usuário não encontrado.", 404);
        }

        return true;
    }

    private static function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }
}