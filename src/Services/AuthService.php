<?php
declare(strict_types=1);

namespace Art\SelecaoNextSi\Services;

use Art\SelecaoNextSi\Repositories\UserRepository;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Exception;
use Throwable;

class AuthService
{
    private const DEFAULT_TOKEN_TTL_SECONDS = 7200;
    private const DUMMY_PASSWORD_HASH = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

    private UserRepository $repository;
    private string $secretKey;

    public function __construct(UserRepository $repository)
    {
        $this->repository = $repository;

        // Falha rápido se a chave de segurança não estiver configurada no ambiente
        $secret = self::getEnvValue('JWT_SECRET');
        if ($secret === '') {
            throw new Exception("Configuração crítica ausente: JWT_SECRET não definido no ambiente.", 500);
        }
        $this->secretKey = $secret;
    }

    /**
     * Valida as credenciais do usuário e retorna o token JWT se sucesso.
     * 
     * @throws Exception Se as credenciais forem inválidas.
     */
    public function authenticate(string $email, string $password): string
    {
        $normalizedEmail = strtolower(trim($email));
        $user = $this->repository->findForAuth($normalizedEmail);

        $hashToVerify = $user['password'] ?? self::DUMMY_PASSWORD_HASH;
        $passwordIsValid = password_verify($password, (string) $hashToVerify);

        // Prevenção de timing attack e enumeração de usuários: a mesma mensagem genérica para e-mail ou senha errados.
        if (!$user || !$passwordIsValid) {
            throw new Exception("Credenciais inválidas.", 401);
        }

        return $this->generateToken($user);
    }

    /**
     * Gera o payload do JWT com os dados mínimos necessários para autorização.
     */
    private function generateToken(array $user): string
    {
        $issuedAt = time();
        $expirationTime = $issuedAt + $this->getTokenTtl();

        $payload = [
            'iat' => $issuedAt,
            'exp' => $expirationTime,
            'sub' => (int) $user['id'],       // Subject (ID do usuário)
            'profile' => $user['profile']     // Profile (necessário para o Middleware de autorização)
        ];

        return JWT::encode($payload, $this->secretKey, 'HS256');
    }

    /**
     * Decodifica e valida o token JWT.
     * 
     * @throws Exception Se o token for inválido, adulterado ou estiver expirado.
     */
    public function validateToken(string $token): object
    {
        try {
            return JWT::decode($token, new Key($this->secretKey, 'HS256'));
        } catch (Throwable $e) {
            // Captura ExpiredException, SignatureInvalidException, etc., da biblioteca do Firebase
            throw new Exception("Token inválido, malformado ou expirado.", 401);
        }
    }

    private function getTokenTtl(): int
    {
        $rawTtl = self::getEnvValue('JWT_TTL_SECONDS');
        $ttl = (int) $rawTtl;

        return $ttl > 0 ? $ttl : self::DEFAULT_TOKEN_TTL_SECONDS;
    }

    private static function getEnvValue(string $key): string
    {
        if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
            return (string) $_ENV[$key];
        }

        if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
            return (string) $_SERVER[$key];
        }

        $value = getenv($key);
        if ($value !== false && $value !== '') {
            return (string) $value;
        }

        return '';
    }
}