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

// 5. Endpoint do OpenAPI JSON (fallback para quando o arquivo não existir)
$app->get('/openapi.json', function (Request $request, Response $response): Response {
    $path = __DIR__ . '/openapi.json';

    if (!file_exists($path)) {
        $payload = json_encode([
            'error' => 'Documentação não gerada. Execute php Scripts/generate-docs.php.'
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $response->getBody()->write($payload !== false ? $payload : '{"error":"Documentação não gerada."}');
        return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
    }

    $content = file_get_contents($path);
    if ($content === false) {
        $response->getBody()->write('{"error":"Falha ao ler a documentação OpenAPI."}');
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }

    $response->getBody()->write($content);
    return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
});

// 6. Swagger UI
$app->get('/docs', function (Request $request, Response $response): Response {
    $html = <<<HTML
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <title>Swagger UI - NextSI</title>
        <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5.11.0/swagger-ui.css" />
    </head>
    <body>
        <div id="swagger-ui"></div>
        <script src="https://unpkg.com/swagger-ui-dist@5.11.0/swagger-ui-bundle.js" crossorigin></script>
        <script>
            window.onload = () => {
                window.ui = SwaggerUIBundle({
                    url: '/openapi.json', // Aponta para o JSON gerado
                    dom_id: '#swagger-ui',
                });
            };
        </script>
    </body>
    </html>
    HTML;

    $response->getBody()->write($html);
    return $response->withHeader('Content-Type', 'text/html');
});


// 7. Roda a aplicação
$app->run();