<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use Slim\Factory\AppFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// Importação das dependências orquestradas
use Art\SelecaoNextSi\Config\Database;
use Art\SelecaoNextSi\Repositories\UserRepository;
use Art\SelecaoNextSi\Services\AuthService;
use Art\SelecaoNextSi\Services\UserService;
use Art\SelecaoNextSi\Controllers\AuthController;
use Art\SelecaoNextSi\Controllers\UserController;
use Art\SelecaoNextSi\Middlewares\AuthMiddleware;
use Art\SelecaoNextSi\Middlewares\AdminMiddleware;

// 1. Configuração de Ambiente
$rootPath = dirname(__DIR__);
if (file_exists($rootPath . '/.env')) {
    Dotenv::createImmutable($rootPath)->safeLoad();
}

$appEnv = $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? getenv('APP_ENV') ?: 'development';
$isDevelopment = $appEnv !== 'production';

$app = AppFactory::create();

// Middleware nativo de Body Parsing e Erros
$app->addBodyParsingMiddleware();
$app->addErrorMiddleware($isDevelopment, true, true);

// 2. Container de Injeção de Dependências Manual
$userRepository = new UserRepository();
$authService    = new AuthService($userRepository);
$userService    = new UserService($userRepository);

$authController = new AuthController($authService);
$userController = new UserController($userService);

$authMiddleware  = new AuthMiddleware($authService);
$adminMiddleware = new AdminMiddleware();

// 3. Rotas Públicas
$app->get('/ping', function (Request $request, Response $response): Response {
    $dbStatus = 'offline';
    try {
        Database::getConnection();
        $dbStatus = 'API e banco de dados funcionais';
    } catch (\Exception $e) {
        $dbStatus = 'API funcionando, mas conexão ao banco falhou';
    }

    $payload = json_encode([
        'api_status' => 'online',
        'db_status'  => $dbStatus,
        'timestamp'  => date('c')
    ]);
    
    $response->getBody()->write($payload);
    return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
});

// Endpoint de Login 
$app->post('/auth/login', [$authController, 'login']);


// 4. Rotas Protegidas 
$app->group('/users', function (\Slim\Routing\RouteCollectorProxy $group) use ($userController, $adminMiddleware) {
    
    // Leitura: Exige apenas estar autenticado (qualquer perfil)
    $group->get('', [$userController, 'index']);
    $group->get('/{id:[0-9]+}', [$userController, 'show']);

    // Mutação: Exige estar autenticado E ter perfil 'admin'
    // Acoplamos o AdminMiddleware especificamente nestas rotas
    $group->post('', [$userController, 'create'])->add($adminMiddleware);
    $group->put('/{id:[0-9]+}', [$userController, 'update'])->add($adminMiddleware);
    $group->delete('/{id:[0-9]+}', [$userController, 'delete'])->add($adminMiddleware);

})->add($authMiddleware); // Aplica a verificação de token (AuthMiddleware) em todo o grupo /users


// 5. Roda a aplicação
$app->run();