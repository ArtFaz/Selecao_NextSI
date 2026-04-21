<?php
declare(strict_types=1);

namespace Art\SelecaoNextSi\Docs;

use OpenApi\Attributes as OA;

#[OA\Info(
    title: "API de Gerenciamento de Usuários",
    version: "1.0.0",
    description: "API para cadastro, autenticação e gerenciamento de usuários.",
)]

#[OA\Server(url: "http://localhost:8080", 
description: "Servidor Local"
)]

#[OA\SecurityScheme(
    securityScheme: "bearerAuth",
    type: "http",
    scheme: "bearer",
    bearerFormat: "JWT"
)]

class OpenApi {}