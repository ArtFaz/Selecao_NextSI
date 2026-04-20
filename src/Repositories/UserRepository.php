<?php
declare(strict_types=1);

namespace Art\SelecaoNextSi\Repositories;

use Art\SelecaoNextSi\Config\Database;
use PDO;

class UserRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Insere um novo usuário.
     */
    public function create(array $data): int
    {
        $sql = "INSERT INTO users (name, email, password, phone, document, profile) 
                VALUES (:name, :email, :password, :phone, :document, :profile)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':name'     => $data['name'],
            ':email'    => $data['email'],
            ':password' => $data['password'],
            ':phone'    => $data['phone'] ?? null,
            ':document' => $data['document'],
            ':profile'  => $data['profile']
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Busca os dados seguros de um usuário pelo E-mail (Sem expor a senha).
     */
    public function findByEmail(string $email): ?array
    {
        $sql = "SELECT id, name, email, phone, document, profile, created_at, updated_at 
                FROM users WHERE email = :email LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':email' => $email]);
        
        $user = $stmt->fetch();
        return $user ?: null;
    }

    /**
     * Busca o usuário com o hash da senha, EXCLUSIVO para autenticação (Login).
     */
    public function findForAuth(string $email): ?array
    {
        $sql = "SELECT id, email, password, profile FROM users WHERE email = :email LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':email' => $email]);
        
        $user = $stmt->fetch();
        return $user ?: null;
    }

    /**
     * Busca usuário pelo Documento.
     */
    public function findByDocument(string $document): ?array
    {
        $sql = "SELECT id, document FROM users WHERE document = :document LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':document' => $document]);
        
        $user = $stmt->fetch();
        return $user ?: null;
    }

    /**
     * Busca um usuário pelo ID seguro.
     */
    public function findById(int $id): ?array
    {
        $sql = "SELECT id, name, email, phone, document, profile, created_at, updated_at 
                FROM users WHERE id = :id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        
        $user = $stmt->fetch();
        return $user ?: null;
    }

    /**
     * Retorna os usuários com suporte a paginação para escalabilidade.
     */
    public function findAll(int $limit = 50, int $offset = 0): array
    {
        $sql = "SELECT id, name, email, phone, document, profile, created_at, updated_at 
                FROM users
                ORDER BY id ASC
                LIMIT :limit OFFSET :offset";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }

    /**
     * Atualiza os dados de um usuário (Incluindo senha opcionalmente).
     */
    public function update(int $id, array $data): bool
    {
        // Se a senha foi passada, atualiza ela também. Se não, ignora.
        if (isset($data['password'])) {
            $sql = "UPDATE users SET name = :name, email = :email, phone = :phone, profile = :profile, password = :password WHERE id = :id";
            $params = [
                ':name'     => $data['name'],
                ':email'    => $data['email'],
                ':phone'    => $data['phone'] ?? null,
                ':profile'  => $data['profile'],
                ':password' => $data['password'],
                ':id'       => $id
            ];
        } else {
            $sql = "UPDATE users SET name = :name, email = :email, phone = :phone, profile = :profile WHERE id = :id";
            $params = [
                ':name'    => $data['name'],
                ':email'   => $data['email'],
                ':phone'   => $data['phone'] ?? null,
                ':profile' => $data['profile'],
                ':id'      => $id
            ];
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        if ($stmt->rowCount() > 0) {
            return true;
        }

        return $this->existsById($id);
    }

    /**
     * Deleta um usuário do sistema checando se ele existia.
     */
    public function delete(int $id): bool
    {
        $sql = "DELETE FROM users WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        
        // rowCount > 0 garante que uma linha foi de fato removida
        return $stmt->rowCount() > 0;
    }

    private function existsById(int $id): bool
    {
        $sql = "SELECT 1 FROM users WHERE id = :id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);

        return (bool) $stmt->fetchColumn();
    }
}