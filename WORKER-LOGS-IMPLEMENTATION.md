# Worker Logs - Implementação

## 📋 Resumo

Sistema de logs separados por tipo de worker para melhor rastreabilidade e debug.

## 🎯 Arquivos de Log Criados

### 1. **invoices.log**
- Localização: `storage/logs/invoices.log`
- Worker: `SyncInvoiceJob` (fila: `invoices`)
- Rotação: Diária (mantém 30 dias)
- Propósito: Logs de sincronização de notas fiscais do Bling

### 2. **tracking.log**
- Localização: `storage/logs/tracking.log`
- Worker: `ExtractTrackingJob` (fila: `tracking`)
- Rotação: Diária (mantém 30 dias)
- Propósito: Logs de extração de códigos de rastreamento

### 3. **sellers.log**
- Localização: `storage/logs/sellers.log`
- Worker: `SyncSellerJob` (fila: `sellers`)
- Rotação: Diária (mantém 30 dias)
- Propósito: Logs de sincronização de vendedores do WooCommerce

### 4. **bling_sync/bling-sync.log** (já existente)
- Localização: `storage/logs/bling_sync/bling-sync.log`
- Worker: `BlingSyncJob`
- Rotação: Diária (mantém 30 dias)
- Propósito: Logs da sincronização principal de pedidos

### 5. **token.log** (já existente)
- Localização: `storage/logs/token.log`
- Worker: `RefreshBlingTokensJob`
- Rotação: Simples
- Propósito: Logs de refresh de tokens OAuth do Bling

### 6. **ping.log** (já existente)
- Localização: `storage/logs/ping.log`
- Worker: `SendHourlyStatsJob`
- Rotação: Diária (mantém 30 dias)
- Propósito: Logs de envio de estatísticas horárias

## 🔧 Traits Criados

### InvoiceLogger
```php
App\Traits\InvoiceLogger
```
Métodos:
- `logInvoiceInfo()`
- `logInvoiceError()`
- `logInvoiceWarning()`
- `logInvoiceDebug()`

### TrackingLogger
```php
App\Traits\TrackingLogger
```
Métodos:
- `logTrackingInfo()`
- `logTrackingError()`
- `logTrackingWarning()`
- `logTrackingDebug()`

### SellerLogger
```php
App\Traits\SellerLogger
```
Métodos:
- `logSellerInfo()`
- `logSellerError()`
- `logSellerWarning()`
- `logSellerDebug()`

## 📝 Configuração (config/logging.php)

```php
'invoices' => [
    'driver' => 'daily',
    'path' => storage_path('logs/invoices.log'),
    'level' => env('LOG_LEVEL', 'info'),
    'days' => 30,
    'replace_placeholders' => true,
],

'tracking' => [
    'driver' => 'daily',
    'path' => storage_path('logs/tracking.log'),
    'level' => env('LOG_LEVEL', 'info'),
    'days' => 30,
    'replace_placeholders' => true,
],

'sellers' => [
    'driver' => 'daily',
    'path' => storage_path('logs/sellers.log'),
    'level' => env('LOG_LEVEL', 'info'),
    'days' => 30,
    'replace_placeholders' => true,
],
```

## 🚀 Workers Separados

Agora o comando `composer dev` inicia **3 workers específicos** em paralelo:

```bash
composer dev
```

Executa:
1. **invoices worker**: `php artisan queue:work --queue=invoices --tries=3`
2. **tracking worker**: `php artisan queue:work --queue=tracking --tries=3`
3. **sellers worker**: `php artisan queue:work --queue=sellers --tries=2`

Cada worker processa sua própria fila e registra logs no seu arquivo específico.

## 📊 Visualização dos Logs

### Por Worker:
```bash
# Invoices
tail -f storage/logs/invoices-*.log

# Tracking
tail -f storage/logs/tracking-*.log

# Sellers
tail -f storage/logs/sellers-*.log

# Bling Sync (principal)
tail -f storage/logs/bling_sync/bling-sync-*.log
```

### Todos os Logs Juntos:
```bash
tail -f storage/logs/*.log storage/logs/bling_sync/*.log
```

### Com Laravel Pail (incluído no composer dev):
O comando `composer dev` já inicia o Laravel Pail automaticamente, que agrupa e formata os logs de todos os canais em tempo real.

## 🎯 Benefícios

1. **Separação de Preocupações**: Cada worker tem seu próprio log
2. **Debug Facilitado**: Fácil identificar problemas específicos de cada tipo
3. **Performance**: Workers paralelos processam as filas simultaneamente
4. **Monitoramento**: Logs isolados permitem análise focada
5. **Escalabilidade**: Possível adicionar mais workers por tipo se necessário

## 📈 Estrutura de Processamento

```
BlingSyncJob (bling_sync.log)
    ↓ emite OrderSynced event
    ├─→ SyncInvoiceJob (invoices.log)
    │       ↓ emite InvoiceSynced event
    │       └─→ ExtractTrackingJob (tracking.log)
    │
    └─→ SyncSellerJob (sellers.log)
```

## 🔍 Monitoramento em Produção

### Supervisor Config (Produção)

```ini
[program:collmepro-invoices-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/artisan queue:work --queue=invoices --tries=3 --timeout=60
directory=/path/to/project
numprocs=2
user=www-data
autostart=true
autorestart=true
stopwaitsecs=3600
stdout_logfile=/path/to/storage/logs/invoices-worker.log

[program:collmepro-tracking-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/artisan queue:work --queue=tracking --tries=3 --timeout=45
directory=/path/to/project
numprocs=2
user=www-data
autostart=true
autorestart=true
stopwaitsecs=3600
stdout_logfile=/path/to/storage/logs/tracking-worker.log

[program:collmepro-sellers-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/artisan queue:work --queue=sellers --tries=2 --timeout=30
directory=/path/to/project
numprocs=1
user=www-data
autostart=true
autorestart=true
stopwaitsecs=3600
stdout_logfile=/path/to/storage/logs/sellers-worker.log
```

## 🛠️ Comandos Úteis

### Desenvolvimento:
```bash
# Iniciar todos os workers e serviços
composer dev

# Iniciar com SSR
composer dev:ssr
```

### Manual (sem composer):
```bash
# Worker de invoices
php artisan queue:work --queue=invoices --tries=3 --timeout=60

# Worker de tracking
php artisan queue:work --queue=tracking --tries=3 --timeout=45

# Worker de sellers
php artisan queue:work --queue=sellers --tries=2 --timeout=30

# Todos juntos
php artisan queue:work --queue=invoices,tracking,sellers
```

### Monitoramento:
```bash
# Ver status das filas
php artisan queue:monitor invoices tracking sellers

# Ver jobs falhados
php artisan queue:failed

# Reprocessar jobs falhados
php artisan queue:retry all
```

## 📌 Notas Importantes

1. **Log Levels**: Configurável via `LOG_LEVEL` no `.env`
2. **Debug Mode**: Logs debug só aparecem com `APP_DEBUG=true`
3. **Rotação**: Logs são rotacionados diariamente (mantém 30 dias)
4. **Sanitização**: Dados sensíveis e pesados são automaticamente removidos dos logs
5. **Performance**: Cada worker roda em processo separado para máxima paralelização

## ✅ Checklist de Verificação

- [x] Canais de log configurados em `config/logging.php`
- [x] Traits de log criados para cada worker
- [x] Jobs atualizados para usar seus próprios loggers
- [x] Comando `composer dev` atualizado para múltiplos workers
- [x] Cada worker tem suas próprias configurações de retry
- [x] Sistema de sanitização de logs implementado
- [x] Documentação completa criada

