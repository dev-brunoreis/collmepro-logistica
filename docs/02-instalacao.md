# Instalacao e Configuracao

## Requisitos do Sistema

### Requisitos Minimos

#### Backend
- PHP 8.2 ou superior
- Composer 2.0+
- MySQL 8.0+ ou SQLite 3.35+
- Redis 6.0+ (opcional, mas recomendado)
- Supervisor (para producao)

#### Frontend
- Node.js 18+ ou 20+
- NPM 9+ ou superior

#### Sistema Operacional
- Linux (recomendado)
- macOS
- Windows (com WSL2 recomendado)

### Extensoes PHP Necessarias
```
- OpenSSL
- PDO
- Mbstring
- Tokenizer
- XML
- Ctype
- JSON
- BCMath
- Fileinfo
- GD ou Imagick
```

## Instalacao

### 1. Clonar o Repositorio

```bash
git clone <repository-url> collmepro-logistica
cd collmepro-logistica
```

### 2. Instalar Dependencias Backend

```bash
composer install
```

### 3. Instalar Dependencias Frontend

```bash
npm install
```

### 4. Configurar Ambiente

Copie o arquivo de exemplo e configure as variaveis:

```bash
cp .env.example .env
```

### 5. Gerar Chave da Aplicacao

```bash
php artisan key:generate
```

### 6. Configurar Banco de Dados

#### Desenvolvimento (SQLite)

O SQLite e usado por padrao em desenvolvimento. Crie o arquivo:

```bash
touch database/database.sqlite
```

#### Producao (MySQL)

Configure as variaveis no `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=collmepro_logistica
DB_USERNAME=seu_usuario
DB_PASSWORD=sua_senha
```

### 7. Executar Migrations

```bash
php artisan migrate
```

### 8. Executar Seeders (Opcional)

Para popular o banco com dados de teste:

```bash
php artisan db:seed
```

## Configuracao do Ambiente (.env)

### Configuracoes da Aplicacao

```env
APP_NAME="Collmepro Logistica"
APP_ENV=local
APP_KEY=base64:...
APP_DEBUG=true
APP_URL=http://localhost
APP_TIMEZONE=America/Sao_Paulo
APP_LOCALE=pt_BR
```

### Configuracoes do Banco de Dados

#### SQLite (Desenvolvimento)
```env
DB_CONNECTION=sqlite
```

#### MySQL (Producao)
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=collmepro_logistica
DB_USERNAME=root
DB_PASSWORD=
```

### Configuracoes de Cache

```env
CACHE_DRIVER=file
# Para producao, use Redis:
# CACHE_DRIVER=redis
```

### Configuracoes de Fila

```env
QUEUE_CONNECTION=database
# Para producao, use Redis:
# QUEUE_CONNECTION=redis
```

### Configuracoes de Broadcasting

```env
BROADCAST_DRIVER=reverb
```

### Configuracoes do Laravel Reverb

```env
REVERB_APP_ID=<app-id>
REVERB_APP_KEY=<app-key>
REVERB_APP_SECRET=<app-secret>
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

### Configuracoes do Bling API

#### Collmepro Pro
```env
BLING_PRO_CLIENT_ID=<client-id>
BLING_PRO_CLIENT_SECRET=<client-secret>
BLING_PRO_CODE=<authorization-code>
```

#### Collmepro Atacadista
```env
BLING_ATACADISTA_CLIENT_ID=<client-id>
BLING_ATACADISTA_CLIENT_SECRET=<client-secret>
BLING_ATACADISTA_CODE=<authorization-code>
```

#### Configuracoes Gerais do Bling
```env
BLING_BASE_URL=https://api.bling.com.br/Api/v3
BLING_TIMEOUT=30
BLING_RATE_LIMIT=0.5
BLING_LOGGING_ENABLED=true
BLING_LOG_REQUESTS=false
BLING_LOG_RESPONSES=false
```

### Configuracoes de Log

```env
LOG_CHANNEL=stack
LOG_LEVEL=debug
```

### Configuracoes de Mail (Opcional)

```env
MAIL_MAILER=smtp
MAIL_HOST=mailhog
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="${APP_NAME}"
```

## Comandos de Desenvolvimento

### Iniciar Servidor de Desenvolvimento Completo

O comando `composer run dev` inicia todos os servicos necessarios:

```bash
composer run dev
```

Este comando executa simultaneamente:
- Laravel Server (porta 8000)
- Queue Worker - Fila `invoices` (3 tentativas)
- Queue Worker - Fila `tracking` (3 tentativas)
- Queue Worker - Fila `sellers` (2 tentativas)
- Laravel Pail (visualizacao de logs)
- Laravel Reverb (WebSockets)
- Vite Dev Server (HMR)

### Iniciar Servicos Individualmente

#### Servidor Laravel
```bash
php artisan serve
```

#### Workers de Fila
```bash
# Fila de invoices
php artisan queue:work --queue=invoices --tries=3

# Fila de tracking
php artisan queue:work --queue=tracking --tries=3

# Fila de sellers
php artisan queue:work --queue=sellers --tries=2
```

#### Laravel Reverb (WebSockets)
```bash
php artisan reverb:start
```

#### Vite (Frontend)
```bash
npm run dev
```

#### Laravel Pail (Logs)
```bash
php artisan pail --timeout=0
```

### Build para Producao

#### Build Frontend
```bash
npm run build
```

#### Build com SSR (Server-Side Rendering)
```bash
npm run build:ssr
```

### Executar com SSR

```bash
composer run dev:ssr
```

## Executar Testes

### Limpar Cache e Executar Testes
```bash
composer run test
```

Ou manualmente:
```bash
php artisan config:clear
php artisan test
```

### Executar Testes Especificos
```bash
php artisan test --filter=NomeDoTeste
```

### Executar com Cobertura
```bash
php artisan test --coverage
```

## Configuracao de Migrations e Seeders

### Criar Nova Migration
```bash
php artisan make:migration create_table_name
```

### Executar Migrations
```bash
php artisan migrate
```

### Reverter Ultima Migration
```bash
php artisan migrate:rollback
```

### Reverter Todas e Reexecutar
```bash
php artisan migrate:fresh
```

### Reverter Todas, Reexecutar e Popular
```bash
php artisan migrate:fresh --seed
```

## Configuracao do Bling OAuth

### 1. Obter Authorization Code

Para cada conta do Bling (Pro e Atacadista), voce precisa obter um authorization code:

```bash
php artisan tinker
```

Dentro do tinker:
```php
// Para Pro
$service = app(\App\Services\CollmeProBlingService::class);
echo $service->getAuthorizationUrl();

// Para Atacadista
$service = app(\App\Services\CollmeAtacadistaBlingService::class);
echo $service->getAuthorizationUrl();
```

Acesse a URL gerada, autorize o aplicativo e copie o codigo retornado.

### 2. Configurar no .env

Adicione os codigos de autorizacao no `.env`:

```env
BLING_PRO_CODE=<authorization-code>
BLING_ATACADISTA_CODE=<authorization-code>
```

### 3. Gerar Tokens Iniciais

Execute o job de refresh de tokens:

```bash
php artisan tinker
```

```php
\App\Jobs\RefreshBlingTokensJob::dispatch();
```

Os tokens serao automaticamente renovados a cada 4 horas via agendador.

## Configuracao do Agendador (Cron)

### Desenvolvimento

Para testar o agendador em desenvolvimento:

```bash
php artisan schedule:work
```

### Producao

Adicione ao crontab:

```bash
crontab -e
```

Adicione a linha:
```
* * * * * cd /caminho/para/collmepro-logistica && php artisan schedule:run >> /dev/null 2>&1
```

## Configuracao do Supervisor (Producao)

Crie o arquivo `/etc/supervisor/conf.d/collmepro-workers.conf`:

```ini
[program:collmepro-invoices]
process_name=%(program_name)s_%(process_num)02d
command=php /caminho/para/collmepro-logistica/artisan queue:work --queue=invoices --tries=3 --timeout=300
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/collmepro-invoices.log
stopwaitsecs=3600

[program:collmepro-tracking]
process_name=%(program_name)s_%(process_num)02d
command=php /caminho/para/collmepro-logistica/artisan queue:work --queue=tracking --tries=3 --timeout=300
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/collmepro-tracking.log
stopwaitsecs=3600

[program:collmepro-sellers]
process_name=%(program_name)s_%(process_num)02d
command=php /caminho/para/collmepro-logistica/artisan queue:work --queue=sellers --tries=2 --timeout=300
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/collmepro-sellers.log
stopwaitsecs=3600

[program:collmepro-reverb]
command=php /caminho/para/collmepro-logistica/artisan reverb:start
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/collmepro-reverb.log
```

Atualize o supervisor:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start all
```

## Verificacao da Instalacao

### 1. Verificar Servicos

Acesse `http://localhost:8000` e verifique se a aplicacao carrega.

### 2. Verificar Logs

```bash
tail -f storage/logs/laravel.log
```

### 3. Verificar Workers

```bash
php artisan queue:monitor
```

### 4. Verificar Reverb

Acesse o console do navegador e verifique se a conexao WebSocket foi estabelecida.

## Troubleshooting

### Erro de Permissao

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

### Cache Corrompido

```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

### Banco de Dados Travado

```bash
php artisan db:wipe
php artisan migrate:fresh --seed
```

### Node Modules Corrompidos

```bash
rm -rf node_modules package-lock.json
npm install
```

