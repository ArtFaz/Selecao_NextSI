# API de Autenticacao e CRUD de Usuarios - Case NextSI

## 1. Objetivo
Este projeto implementa uma API RESTful para autenticacao e gerenciamento de usuarios, conforme o case da NextSI.

Escopo funcional:
- Login com JWT
- CRUD de usuarios
- Controle de acesso por perfil
- Documentacao OpenAPI/Swagger
- Ambiente de execucao com Docker, pronto para avaliacao tecnica

## 2. Visao Geral da Solucao
A aplicacao foi estruturada em camadas para separar responsabilidades:
- Controllers: camada HTTP (entrada/saida, validacoes de payload e status codes)
- Services: regras de negocio
- Repositories: acesso ao banco com PDO e prepared statements
- Middlewares: autenticacao JWT e autorizacao por perfil
- Docs: metadados e anotacoes OpenAPI

Stack principal:
- PHP 8.5
- Slim Framework 4
- MySQL 8.0
- JWT (firebase/php-jwt)
- Swagger/OpenAPI (zircote/swagger-php com Attributes)
- Docker (Nginx + PHP-FPM + MySQL)

## 3. Requisitos Atendidos do Case
- Linguagem PHP e persistencia em MySQL
- Endpoint de login com autenticacao por email e senha
- Endpoints para criar, listar, visualizar, atualizar e excluir usuarios
- Restricao de acesso:
  - rotas de leitura autenticadas
  - rotas de mutacao permitidas apenas para admin
- Senha armazenada com hash seguro
- Validacao de documento CPF/CNPJ
- Documentacao via Swagger/OpenAPI
- Projeto versionado com estrutura organizada

## 4. Arquitetura e Fluxo
Fluxo de requisicao:
1. Cliente chama a API via Nginx
2. Nginx encaminha para o front controller em public/index.php
3. Slim resolve rota e middlewares
4. Controller recebe entrada e delega para Service
5. Service executa regra de negocio e chama Repository
6. Repository persiste/consulta no MySQL
7. Controller retorna JSON padronizado

Middlewares:
- AuthMiddleware: valida token Bearer e injeta user_id e user_profile na requisicao
- AdminMiddleware: bloqueia operacoes de mutacao para perfis nao admin

## 5. Estrutura de Pastas
```
public/              # Entrypoint HTTP e rotas
src/Config/          # Configuracoes (ex.: conexao com banco)
src/Controllers/     # Camada HTTP
src/Services/        # Regras de negocio
src/Repositories/    # Acesso a dados
src/Middlewares/     # Auth e autorizacao
src/Docs/            # Metadados OpenAPI
database/            # Script de inicializacao SQL
Scripts/             # Scripts utilitarios (geracao OpenAPI)
nginx/               # Configuracao do servidor web
```

## 6. Modelo de Dados
Tabela principal: users
- id
- name
- email (unique)
- password (hash)
- phone
- document (unique)
- profile (admin | user)
- created_at
- updated_at

Seed inicial:
- usuario admin criado no bootstrap do banco
- email: admin@nextsi.com.br
- senha (ambiente de desenvolvimento): Admin@123

## 7. Endpoints da API
Publicos:
- GET /ping
- POST /auth/login

Protegidos (JWT):
- GET /users
- GET /users/{id}

Protegidos (JWT + admin):
- POST /users
- PUT /users/{id}
- DELETE /users/{id}

## 8. Autenticacao e Autorizacao
### Login
Envie email e password para /auth/login.
Em caso de sucesso, a API retorna token JWT.

### Uso do token
Enviar no header Authorization:
```
Authorization: Bearer SEU_TOKEN
```

### Regras de perfil
- profile = user: acesso apenas a leitura autenticada
- profile = admin: acesso total (leitura e mutacao)

## 9. Swagger / OpenAPI
A documentacao esta disponivel em:
- UI: /docs
- JSON: /openapi.json

Comportamento atual:
- O container app gera openapi.json automaticamente no startup
- Existe fallback de rota para /openapi.json caso o arquivo ainda nao exista

Geracao manual (opcional):
```
php Scripts/generate-docs.php
```

## 10. Variaveis de Ambiente
Baseado em .env.example:
- APP_ENV
- DB_HOST
- DB_PORT
- DB_DATABASE
- DB_USER
- DB_PASSWORD
- MYSQL_ROOT_PASSWORD
- MYSQL_DATABASE
- MYSQL_USER
- MYSQL_PASSWORD
- JWT_SECRET
- JWT_TTL_SECONDS

Observacao:
- .env nao deve ser versionado
- .env.example e versionado para setup rapido

## 11. Como Executar (Docker)
### Pre-requisitos
- Docker
- Docker Compose

### Passo a passo
1. Criar arquivo de ambiente:
```
cp .env.example .env
```

No Windows PowerShell, alternativa:
```
Copy-Item .env.example .env
```

2. Subir os servicos:
```
docker compose up --build -d
```

Observacao sobre a primeira execucao em maquina limpa:
- O servico app instala automaticamente as dependencias do Composer caso o diretório vendor ainda nao exista.
- Por isso, o primeiro startup pode levar mais tempo.
- Nao e necessario executar manualmente `docker compose exec app composer install`.

3. Validar status:
```
docker compose ps
```

4. Acessar:
- API: http://localhost:8080
- Healthcheck: http://localhost:8080/ping
- Swagger UI: http://localhost:8080/docs

### Parar ambiente
```
docker compose down
```

## 12. Exemplo Rapido de Login
Request:
```
POST /auth/login
Content-Type: application/json

{
  "email": "admin@nextsi.com.br",
  "password": "Admin@123"
}
```

Response esperado:
```
{
  "token": "..."
}
```

## 13. Decisoes Tecnicas Relevantes
- Slim 4 para manter controle arquitetural sem framework monolitico
- PDO com prepared statements e emulacao desativada
- Hash de senha com password_hash/password_verify
- JWT com exp e claim de profile para autorizacao
- Validacao de CPF/CNPJ no dominio
- Paginacao em listagem com limit e offset
- Swagger com Attributes para manter documentacao proxima ao codigo

## 14. Observacoes para Avaliacao
- Projeto preparado para execucao local isolada via Docker
- Seed de admin facilita validacao imediata dos fluxos protegidos
- Estrutura em camadas favorece manutencao e evolucao
- Foco em seguranca aplicada ao escopo do case

## 15. Possiveis Evolucoes
- Testes automatizados unitarios e de integracao
- BaseController para consolidar helper JSON entre controllers
- Pipeline CI para lint, testes e geracao de documentacao
- Estrategia de refresh token e revogacao de JWT
