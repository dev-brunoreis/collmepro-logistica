# Manutencao e Troubleshooting

## Visao Geral

Este documento fornece guias de manutencao e solucoes para problemas comuns do sistema.

## Logs do Sistema

### Localizacao dos Logs

```bash
# Logs gerais do Laravel
storage/logs/laravel.log

# Logs de sincronizacao Bling
storage/logs/bling-sync.log

# Logs dos workers
storage/logs/worker-invoices.log
storage/logs/worker-tracking.log
storage/logs/worker-sellers.log
storage/logs/worker-default.log

# Logs do Reverb (WebSockets)
storage/logs/reverb.log

# Logs do Nginx
/var/log/nginx/collmepro-access.log
/var/log/nginx/collmepro-error.log

# Logs do PHP-FPM
/var/log/php8.2-fpm.log
```

### Visualizar Logs em Tempo Real

```bash
# Laravel geral
tail -f storage/logs/laravel.log

# Bling sync
tail -f storage/logs/bling-sync.log

# Multiplos logs
tail -f storage/logs/*.log

# Com filtro
tail -f storage/logs/laravel.log | grep ERROR
```

### Laravel Pail (Desenvolvimento)

```bash
php artisan pail --timeout=0
```

## Problemas Comuns e Solucoes

### 1. Sincronizacao Bling Nao Funciona

#### Sintomas
- Pedidos nao sao importados
- Dashboard mostra "Sync stopped"
- Logs mostram erros de autenticacao

#### Verificacoes

**1. Verificar tokens OAuth**

```bash
php artisan tinker
```

```php
$tokens = \App\Models\BlingToken::all();
foreach ($tokens as $token) {
    echo "Account: {$token->account_type}\n";
    echo "Expires: {$token->expires_at}\n";
    echo "Valid: " . ($token->expires_at->isFuture() ? 'Yes' : 'No') . "\n\n";
}
```

**2. Forcar refresh de tokens**

```php
\App\Jobs\RefreshBlingTokensJob::dispatchSync();
```

**3. Verificar configuracoes no .env**

```env
BLING_PRO_CLIENT_ID=...
BLING_PRO_CLIENT_SECRET=...
BLING_ATACADISTA_CLIENT_ID=...
BLING_ATACADISTA_CLIENT_SECRET=...
```

**4. Verificar logs**

```bash
tail -f storage/logs/bling-sync.log | grep ERROR
```

#### Solucoes

**Tokens expirados**

```bash
# Regenerar tokens manualmente
php artisan tinker
```

```php
$service = app(\App\Services\CollmeProBlingService::class);
echo $service->getAuthorizationUrl();
// Acesse a URL, autorize e copie o codigo
// Atualize .env com BLING_PRO_CODE
// Execute refresh
\App\Jobs\RefreshBlingTokensJob::dispatchSync();
```

**Rate limit excedido**

- Aguardar 10 minutos
- Verificar se ha multiplos workers rodando desnecessariamente
- O sistema ja usa rate limiting conservador, nao deve ocorrer

**Erro de conexao**

```bash
# Testar conectividade
curl -I https://api.bling.com.br/Api/v3

# Verificar DNS
nslookup api.bling.com.br

# Verificar firewall
sudo ufw status
```

### 2. Workers Nao Estao Processando Jobs

#### Sintomas
- Jobs ficam pendentes na fila
- Pedidos nao sao atualizados
- Notas fiscais nao sao sincronizadas

#### Verificacoes

**1. Status dos workers**

```bash
sudo supervisorctl status
```

**2. Verificar filas**

```bash
php artisan queue:monitor
```

**3. Verificar jobs falhados**

```bash
php artisan tinker
```

```php
DB::table('failed_jobs')->count();
DB::table('failed_jobs')->latest()->first();
```

#### Solucoes

**Workers parados**

```bash
sudo supervisorctl start all
sudo supervisorctl restart all
```

**Memoria insuficiente**

```bash
# Verificar uso de memoria
free -h

# Reiniciar workers com limite
php artisan queue:work --max-jobs=100 --max-time=3600
```

**Jobs falhados**

```bash
# Ver detalhes do job falhado
php artisan queue:failed

# Retry de todos os jobs falhados
php artisan queue:retry all

# Retry de job especifico
php artisan queue:retry {job-id}

# Limpar jobs falhados
php artisan queue:flush
```

**Fila travada**

```bash
# Limpar fila
php artisan queue:clear redis

# Reiniciar Redis
sudo systemctl restart redis-server

# Reiniciar workers
sudo supervisorctl restart all
```

### 3. WebSockets Nao Funcionam

#### Sintomas
- Dashboard nao atualiza em tempo real
- Kanban nao reflete mudancas automaticamente
- Console do navegador mostra erro de conexao WebSocket

#### Verificacoes

**1. Verificar se Reverb esta rodando**

```bash
ps aux | grep reverb
sudo supervisorctl status collmepro-reverb
```

**2. Verificar porta**

```bash
netstat -tulpn | grep 8080
```

**3. Testar conexao WebSocket**

No console do navegador:

```javascript
console.log(window.Echo);
```

#### Solucoes

**Reverb parado**

```bash
sudo supervisorctl start collmepro-reverb
sudo supervisorctl restart collmepro-reverb
```

**Configuracao incorreta**

Verificar `.env`:

```env
BROADCAST_DRIVER=reverb
REVERB_APP_ID=...
REVERB_APP_KEY=...
REVERB_APP_SECRET=...
REVERB_HOST=logistica.collmepro.com
REVERB_PORT=443
REVERB_SCHEME=https
```

Verificar configuracao Nginx para proxy WebSocket.

**Firewall bloqueando**

```bash
# Liberar porta 8080 (ou 443 se via proxy)
sudo ufw allow 8080/tcp
```

### 4. Erros de Permissao

#### Sintomas
- Erro 500 ao acessar aplicacao
- Erro ao fazer upload de arquivos
- Erro ao escrever logs

#### Verificacoes

```bash
ls -la storage/
ls -la bootstrap/cache/
```

#### Solucoes

```bash
# Corrigir ownership
sudo chown -R www-data:www-data storage bootstrap/cache

# Corrigir permissoes
sudo chmod -R 775 storage bootstrap/cache

# Recursivo para todos os subdiretorios
sudo find storage -type f -exec chmod 664 {} \;
sudo find storage -type d -exec chmod 775 {} \;
```

### 5. Cache Corrompido

#### Sintomas
- Erros estranhos apos atualizacao
- Configuracoes nao aplicadas
- Rotas nao funcionam

#### Solucoes

```bash
# Limpar todos os caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Recriar caches
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Otimizar autoloader
composer dump-autoload --optimize
```

### 6. Banco de Dados Lento

#### Sintomas
- Requisicoes lentas
- Timeouts
- Alto uso de CPU

#### Verificacoes

**1. Verificar queries lentas**

```sql
SHOW PROCESSLIST;
SELECT * FROM information_schema.processlist WHERE time > 10;
```

**2. Verificar indices**

```sql
SHOW INDEX FROM order_summary;
SHOW INDEX FROM invoices;
```

**3. Analisar query especifica**

```sql
EXPLAIN SELECT * FROM order_summary WHERE data > '2025-01-01';
```

#### Solucoes

**Otimizar tabelas**

```sql
OPTIMIZE TABLE order_summary;
OPTIMIZE TABLE invoices;
OPTIMIZE TABLE tracking;
```

**Adicionar indices faltantes**

```sql
CREATE INDEX idx_data_source ON order_summary(data, source);
CREATE INDEX idx_invoice_emissao ON invoices(data_emissao);
```

**Limpar dados antigos**

```sql
-- Cuidado: fazer backup antes!
DELETE FROM order_histories WHERE created_at < DATE_SUB(NOW(), INTERVAL 6 MONTH);
DELETE FROM failed_jobs WHERE failed_at < DATE_SUB(NOW(), INTERVAL 1 MONTH);
```

### 7. Memoria Insuficiente

#### Sintomas
- Erro "Allowed memory size exhausted"
- Workers morrem frequentemente
- PHP-FPM crashando

#### Verificacoes

```bash
free -h
top
ps aux --sort=-%mem | head
```

#### Solucoes

**Aumentar limite de memoria PHP**

Editar `/etc/php/8.2/fpm/php.ini`:

```ini
memory_limit = 512M
```

Reiniciar PHP-FPM:

```bash
sudo systemctl restart php8.2-fpm
```

**Limitar workers**

Editar configuracao Supervisor:

```ini
[program:collmepro-invoices]
command=php artisan queue:work --max-jobs=500 --max-time=3600
```

**Adicionar swap**

```bash
sudo fallocate -l 2G /swapfile
sudo chmod 600 /swapfile
sudo mkswap /swapfile
sudo swapon /swapfile

# Permanente
echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab
```

## Comandos Uteis

### Laravel

```bash
# Limpar cache
php artisan cache:clear

# Modo de manutencao
php artisan down
php artisan up

# Ver rotas
php artisan route:list

# Ver jobs na fila
php artisan queue:monitor

# Executar migrations
php artisan migrate

# Rollback migrations
php artisan migrate:rollback

# Seed database
php artisan db:seed

# Tinker (REPL)
php artisan tinker
```

### Supervisor

```bash
# Status
sudo supervisorctl status

# Iniciar todos
sudo supervisorctl start all

# Parar todos
sudo supervisorctl stop all

# Reiniciar todos
sudo supervisorctl restart all

# Reiniciar um especifico
sudo supervisorctl restart collmepro-invoices:*

# Recarregar configuracao
sudo supervisorctl reread
sudo supervisorctl update
```

### MySQL

```bash
# Conectar
mysql -u collmepro -p collmepro_logistica

# Backup
mysqldump -u collmepro -p collmepro_logistica > backup.sql

# Restaurar
mysql -u collmepro -p collmepro_logistica < backup.sql
```

### Redis

```bash
# Conectar
redis-cli

# Ver keys
redis-cli KEYS '*'

# Limpar tudo
redis-cli FLUSHALL

# Ver memoria
redis-cli INFO memory
```

### Nginx

```bash
# Testar configuracao
sudo nginx -t

# Recarregar
sudo systemctl reload nginx

# Reiniciar
sudo systemctl restart nginx

# Status
sudo systemctl status nginx
```

## Debug de Integrações

### Bling API

**Testar autenticacao**

```bash
php artisan tinker
```

```php
$service = app(\App\Services\CollmeProBlingService::class);
$token = $service->getValidToken();
echo "Token: " . substr($token, 0, 20) . "...\n";
```

**Testar requisicao**

```php
$orders = $service->getAllOrders([], false, 1);
print_r($orders);
```

**Ver logs detalhados**

Temporariamente ativar no `.env`:

```env
BLING_LOG_REQUESTS=true
BLING_LOG_RESPONSES=true
```

### WebSocket

**Testar no console do navegador**

```javascript
// Ver se Echo esta conectado
console.log(window.Echo.connector.pusher.connection.state);

// Escutar eventos
window.Echo.channel('orders').listen('OrderCreated', (e) => {
    console.log('Event received:', e);
});
```

## Limpeza de Cache

### Desenvolvimento

```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

### Producao

```bash
# Limpar
php artisan cache:clear
php artisan config:clear

# Recriar
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Regeneracao de Tokens Bling

### Processo Completo

1. **Obter URL de autorizacao**

```bash
php artisan tinker
```

```php
$service = app(\App\Services\CollmeProBlingService::class);
echo $service->getAuthorizationUrl();
```

2. **Acessar URL e autorizar**

Copiar codigo de retorno

3. **Atualizar .env**

```env
BLING_PRO_CODE=codigo_copiado
```

4. **Executar refresh**

```php
\App\Jobs\RefreshBlingTokensJob::dispatchSync();
```

5. **Verificar**

```php
$token = \App\Models\BlingToken::where('account_type', 'pro')->first();
echo "Expires: " . $token->expires_at . "\n";
echo "Valid: " . ($token->expires_at->isFuture() ? 'Yes' : 'No') . "\n";
```

## Monitoramento de Saude

### Script de Verificacao

Criar `/usr/local/bin/check-collmepro.sh`:

```bash
#!/bin/bash

echo "=== Collmepro Health Check ==="

# Verificar workers
echo -e "\n1. Workers Status:"
sudo supervisorctl status | grep collmepro

# Verificar Redis
echo -e "\n2. Redis:"
redis-cli ping

# Verificar MySQL
echo -e "\n3. MySQL:"
mysql -u collmepro -p'senha' -e "SELECT 1" 2>/dev/null && echo "OK" || echo "FAIL"

# Verificar disco
echo -e "\n4. Disk Space:"
df -h | grep "/$"

# Verificar memoria
echo -e "\n5. Memory:"
free -h

# Verificar jobs falhados
echo -e "\n6. Failed Jobs:"
php /var/www/collmepro-logistica/artisan tinker --execute="echo DB::table('failed_jobs')->count();"
```

Executar:

```bash
sudo chmod +x /usr/local/bin/check-collmepro.sh
/usr/local/bin/check-collmepro.sh
```

## Contatos de Suporte

- **Desenvolvedor**: Bruno Reis - contato@brunoreis.dev
- **Bling API**: https://developer.bling.com.br/
- **Laravel**: https://laravel.com/docs

