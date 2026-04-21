<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use OpenApi\Generator;

$generator = new Generator();

// Escaneia a pasta src e gera a documentação
$openapi = $generator->generate([__DIR__ . '/../src']);

if ($openapi !== null) {
    file_put_contents(__DIR__ . '/../public/openapi.json', $openapi->toJson());
    echo "✅ Documentação gerada com sucesso em public/openapi.json\n";
} else {
    echo "❌ Falha ao gerar a documentação.\n";
}