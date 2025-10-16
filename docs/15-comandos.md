# Referencia de Comandos

## Visao Geral

Este documento lista todos os comandos disponiveis no sistema para desenvolvimento, manutencao e operacao.

## Comandos Artisan (Laravel)

### Aplicacao

#### Modo de Manutencao

```bash
# Ativar modo de manutencao
php artisan down

# Ativar com mensagem customizada
php artisan down --message="Sistema em manutencao" --retry=60

# Desativar modo de manutencao
php artisan up
```

#### Informacoes

```bash
# Versao do Laravel
php artisan --version

# Listar todos os comandos
php artisan list

# Ajuda de um comando especifico
php artisan help queue:work
```

### Cache

```bash
# Limpar cache da aplicacao
php artisan cache:clear

# Limpar cache de configuracao
php artisan config:clear

# Limpar cache de rotas
php artisan route:clear

# Limpar cache de views
php artisan view:clear

# Limpar todos os caches
php artisan optimize:clear

# Criar cache de configuracao (producao)
php artisan config:cache

# Criar cache de rotas (producao)
php artisan route:cache

# Criar cache de views (producao)
php artisan view:cache

# Otimizar aplicacao para producao
php artisan optimize
```

### Banco de Dados

```bash
# Executar migrations
php artisan migrate

# Executar migrations (producao, sem confirmacao)
php artisan migrate --force

# Criar nova migration
php artisan make:migration create_table_name

# Rollback ultima migration
php artisan migrate:rollback

# Rollback todas as migrations
php artisan migrate:reset

# Rollback e reexecutar todas as migrations
php artisan migrate:refresh

# Limpar banco e reexecutar migrations
php artisan migrate:fresh

# Limpar banco, reexecutar migrations e seeders
php artisan migrate:fresh --seed

# Executar seeders
php artisan db:seed

# Executar seeder especifico
php artisan db:seed --class=UserSeeder

# Verificar status das migrations
php artisan migrate:status

# Limpar banco de dados
php artisan db:wipe
```

### Queue (Filas)

```bash
# Processar jobs (fila default)
php artisan queue:work

# Processar fila especifica
php artisan queue:work --queue=invoices

# Com opcoes de tentativas e timeout
php artisan queue:work --queue=invoices --tries=3 --timeout=300

# Limitar jobs e tempo
php artisan queue:work --max-jobs=1000 --max-time=3600

# Parar apos processar todos os jobs
php artisan queue:work --stop-when-empty

# Processar um unico job
php artisan queue:work --once

# Monitorar filas
php artisan queue:monitor redis:default,redis:invoices

# Ver jobs falhados
php artisan queue:failed

# Retry job falhado especifico
php artisan queue:retry {job-id}

# Retry todos os jobs falhados
php artisan queue:retry all

# Deletar job falhado
php artisan queue:forget {job-id}

# Limpar jobs falhados
php artisan queue:flush

# Limpar fila especifica
php artisan queue:clear redis --queue=invoices

# Reiniciar workers
php artisan queue:restart
```

### Scheduler

```bash
# Executar tarefas agendadas (para cron)
php artisan schedule:run

# Listar tarefas agendadas
php artisan schedule:list

# Executar scheduler continuamente (desenvolvimento)
php artisan schedule:work
```

### Logs

```bash
# Visualizar logs em tempo real
php artisan pail

# Com timeout infinito
php artisan pail --timeout=0

# Filtrar por nivel
php artisan pail --level=error
```

### Reverb (WebSockets)

```bash
# Iniciar servidor Reverb
php artisan reverb:start

# Com host e porta especificos
php artisan reverb:start --host=0.0.0.0 --port=8080

# Debug mode
php artisan reverb:start --debug
```

### Rotas

```bash
# Listar todas as rotas
php artisan route:list

# Filtrar por nome
php artisan route:list --name=orders

# Filtrar por metodo
php artisan route:list --method=GET

# Filtrar por URI
php artisan route:list --path=dashboard

# Apenas rotas com middleware especifico
php artisan route:list --middleware=auth
```

### Tinker (REPL)

```bash
# Iniciar tinker
php artisan tinker

# Executar comando diretamente
php artisan tinker --execute="User::count()"
```

### Geradores

```bash
# Controller
php artisan make:controller OrderController

# Model
php artisan make:model Order

# Model com migration, factory e seeder
php artisan make:model Order -mfs

# Migration
php artisan make:migration create_orders_table

# Seeder
php artisan make:seeder UserSeeder

# Factory
php artisan make:factory OrderFactory

# Job
php artisan make:job ProcessOrder

# Event
php artisan make:event OrderCreated

# Listener
php artisan make:listener SendOrderNotification

# Middleware
php artisan make:middleware CheckPermission

# Request
php artisan make:request StoreOrderRequest

# Resource
php artisan make:resource OrderResource

# Policy
php artisan make:policy OrderPolicy

# Command
php artisan make:command SyncOrders
```

### Testes

```bash
# Executar todos os testes
php artisan test

# Executar testes com cobertura
php artisan test --coverage

# Cobertura minima
php artisan test --coverage --min=60

# Filtrar teste especifico
php artisan test --filter=test_user_can_login

# Executar suite especifica
php artisan test --testsuite=Feature

# Parar no primeiro erro
php artisan test --stop-on-failure

# Executar testes em paralelo
php artisan test --parallel
```

### Outros

```bash
# Gerar chave da aplicacao
php artisan key:generate

# Limpar e recompilar autoloader
php artisan clear-compiled
composer dump-autoload

# Publicar assets de pacotes
php artisan vendor:publish

# Storage link
php artisan storage:link

# Ver ambiente
php artisan env

# Ver informacoes sobre
php artisan about
```

## Comandos NPM

### Desenvolvimento

```bash
# Instalar dependencias
npm install

# Instalar dependencias (producao)
npm ci

# Iniciar dev server (HMR)
npm run dev

# Build para producao
npm run build

# Build com SSR
npm run build:ssr
```

### Linting e Formatacao

```bash
# ESLint
npx eslint resources/js

# Prettier
npx prettier --write resources/js
```

## Comandos Composer

### Dependencias

```bash
# Instalar dependencias
composer install

# Instalar sem dev
composer install --no-dev --optimize-autoloader

# Atualizar dependencias
composer update

# Atualizar pacote especifico
composer update laravel/framework

# Adicionar pacote
composer require package/name

# Remover pacote
composer remove package/name

# Dump autoload
composer dump-autoload

# Otimizar autoload
composer dump-autoload --optimize
```

### Scripts Customizados

```bash
# Desenvolvimento completo (server + workers + vite)
composer run dev

# Desenvolvimento com SSR
composer run dev:ssr

# Executar testes
composer run test
```

## Comandos Git

### Basicos

```bash
# Status
git status

# Adicionar arquivos
git add .
git add arquivo.php

# Commit
git commit -m "mensagem"

# Push
git push origin main

# Pull
git pull origin main

# Ver historico
git log
git log --oneline
```

### Branches

```bash
# Listar branches
git branch

# Criar branch
git branch feature/nova-funcionalidade

# Mudar de branch
git checkout feature/nova-funcionalidade

# Criar e mudar
git checkout -b feature/nova-funcionalidade

# Deletar branch
git branch -d feature/nova-funcionalidade

# Merge
git merge feature/nova-funcionalidade
```

## Comandos MySQL

### Conexao

```bash
# Conectar
mysql -u usuario -p

# Conectar diretamente no banco
mysql -u usuario -p nome_banco
```

### Backup e Restore

```bash
# Backup completo
mysqldump -u usuario -p nome_banco > backup.sql

# Backup apenas estrutura
mysqldump -u usuario -p --no-data nome_banco > structure.sql

# Backup apenas dados
mysqldump -u usuario -p --no-create-info nome_banco > data.sql

# Backup com compressao
mysqldump -u usuario -p nome_banco | gzip > backup.sql.gz

# Restore
mysql -u usuario -p nome_banco < backup.sql

# Restore comprimido
gunzip < backup.sql.gz | mysql -u usuario -p nome_banco
```

### Dentro do MySQL

```sql
-- Listar bancos
SHOW DATABASES;

-- Usar banco
USE collmepro_logistica;

-- Listar tabelas
SHOW TABLES;

-- Estrutura da tabela
DESCRIBE order_summary;

-- Ver processlist
SHOW PROCESSLIST;

-- Ver variaveis
SHOW VARIABLES LIKE 'max_connections';

-- Otimizar tabela
OPTIMIZE TABLE order_summary;

-- Analisar query
EXPLAIN SELECT * FROM orders WHERE data > '2025-01-01';
```

## Comandos Redis

```bash
# Conectar
redis-cli

# Conectar com senha
redis-cli -a senha

# Ping
redis-cli ping

# Ver keys
redis-cli KEYS '*'

# Ver key especifica
redis-cli GET key_name

# Deletar key
redis-cli DEL key_name

# Limpar tudo
redis-cli FLUSHALL

# Limpar database atual
redis-cli FLUSHDB

# Ver informacoes
redis-cli INFO

# Ver memoria
redis-cli INFO memory

# Ver stats
redis-cli INFO stats

# Monitor em tempo real
redis-cli MONITOR
```

## Comandos Supervisor

```bash
# Status de todos os programas
sudo supervisorctl status

# Status de programa especifico
sudo supervisorctl status collmepro-invoices:*

# Iniciar programa
sudo supervisorctl start collmepro-invoices:*

# Parar programa
sudo supervisorctl stop collmepro-invoices:*

# Reiniciar programa
sudo supervisorctl restart collmepro-invoices:*

# Iniciar todos
sudo supervisorctl start all

# Parar todos
sudo supervisorctl stop all

# Reiniciar todos
sudo supervisorctl restart all

# Reler configuracao
sudo supervisorctl reread

# Atualizar com nova configuracao
sudo supervisorctl update

# Ver logs
sudo supervisorctl tail collmepro-invoices
sudo supervisorctl tail -f collmepro-invoices
```

## Comandos Nginx

```bash
# Testar configuracao
sudo nginx -t

# Recarregar configuracao
sudo systemctl reload nginx

# Reiniciar
sudo systemctl restart nginx

# Parar
sudo systemctl stop nginx

# Iniciar
sudo systemctl start nginx

# Status
sudo systemctl status nginx

# Ver logs de erro
sudo tail -f /var/log/nginx/error.log

# Ver logs de acesso
sudo tail -f /var/log/nginx/access.log
```

## Comandos Sistema (Linux)

### Processos

```bash
# Ver processos
ps aux

# Filtrar processo
ps aux | grep php

# Top (uso de CPU/memoria)
top

# Htop (se instalado)
htop

# Matar processo
kill PID
kill -9 PID

# Matar por nome
pkill -f "queue:work"
```

### Disco

```bash
# Espaco em disco
df -h

# Uso de diretorios
du -sh /var/www/*

# Maior arquivos
find /var/www -type f -size +100M -exec ls -lh {} \;
```

### Memoria

```bash
# Memoria livre
free -h

# Memoria detalhada
cat /proc/meminfo
```

### Rede

```bash
# Portas em uso
netstat -tulpn

# Alternativa moderna
ss -tulpn

# Testar conexao
curl -I https://api.bling.com.br
ping api.bling.com.br

# DNS lookup
nslookup api.bling.com.br
```

### Permissoes

```bash
# Mudar owner
sudo chown -R www-data:www-data /var/www/collmepro-logistica

# Mudar permissoes
sudo chmod -R 755 /var/www/collmepro-logistica

# Recursivo para arquivos
sudo find /var/www -type f -exec chmod 644 {} \;

# Recursivo para diretorios
sudo find /var/www -type d -exec chmod 755 {} \;
```

### Servicos

```bash
# Status de servico
sudo systemctl status nginx

# Iniciar servico
sudo systemctl start nginx

# Parar servico
sudo systemctl stop nginx

# Reiniciar servico
sudo systemctl restart nginx

# Recarregar servico
sudo systemctl reload nginx

# Habilitar no boot
sudo systemctl enable nginx

# Desabilitar no boot
sudo systemctl disable nginx
```

## Atalhos e Aliases Uteis

Adicionar ao `~/.bashrc` ou `~/.zshrc`:

```bash
# Laravel
alias pa='php artisan'
alias pam='php artisan migrate'
alias pas='php artisan serve'
alias pat='php artisan tinker'
alias paq='php artisan queue:work'

# Composer
alias ci='composer install'
alias cu='composer update'
alias cda='composer dump-autoload'

# NPM
alias nr='npm run'
alias nrd='npm run dev'
alias nrb='npm run build'

# Git
alias gs='git status'
alias ga='git add .'
alias gc='git commit -m'
alias gp='git push'
alias gl='git log --oneline'

# Logs
alias laralogs='tail -f storage/logs/laravel.log'
alias blinglogs='tail -f storage/logs/bling-sync.log'

# Supervisor
alias sps='sudo supervisorctl status'
alias spr='sudo supervisorctl restart all'
```

Aplicar:

```bash
source ~/.bashrc
# ou
source ~/.zshrc
```

