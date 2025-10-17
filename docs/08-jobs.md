# Jobs e Processamento em Background

## Visao Geral

O sistema utiliza Laravel Queue para processar tarefas em background, garantindo que operacoes demoradas nao bloqueiem a interface do usuario. O sistema possui 4 filas especializadas para diferentes tipos de trabalho.

## Filas e Workers

### Configuracao de Filas

#### invoices (Fila de Notas Fiscais)
- **Workers**: 2
- **Tries**: 3
- **Timeout**: 300 segundos
- **Uso**: Sincronizacao de notas fiscais do Bling

#### tracking (Fila de Rastreamento)
- **Workers**: 2
- **Tries**: 3
- **Timeout**: 300 segundos
- **Uso**: Extracao de dados de rastreamento

#### sellers (Fila de Vendedores)
- **Workers**: 1
- **Tries**: 2
- **Timeout**: 300 segundos
- **Uso**: Sincronizacao de vendedores do WooCommerce

#### default (Fila Padrao)
- **Workers**: 1
- **Tries**: 3
- **Timeout**: 300 segundos
- **Uso**: Jobs gerais, incluindo BlingSyncJob

### Comando de Execucao

```bash
# Fila de invoices
php artisan queue:work --queue=invoices --tries=3 --timeout=300

# Fila de tracking
php artisan queue:work --queue=tracking --tries=3 --timeout=300

# Fila de sellers
php artisan queue:work --queue=sellers --tries=2 --timeout=300

# Fila default
php artisan queue:work --tries=3 --timeout=300
```

## Jobs Principais

### BlingSyncJob

**Arquivo**: `app/Jobs/BlingSyncJob.php`

**Descricao**: Job principal que sincroniza pedidos do Bling.

**Fila**: `default`

**Parametros**:
- `forceUpdate` (bool): Forcar atualizacao de pedidos existentes
- `accountType` (string|null): Tipo de conta ('pro', 'atacadista', ou null para todas)
- `incrementalSync` (bool): Sincronizacao incremental (ultimos 30 dias)
- `maxPagesPerExecution` (int): Maximo de paginas por execucao (default: 10)

**Configuracao**:
```php
public int $timeout = 3600; // 1 hora
public int $tries = 5;
public array $backoff = [60, 300, 600, 1800]; // 1min, 5min, 10min, 30min
```

**Fluxo de Execucao**:

1. **Inicializacao**
   - Atualiza status de sincronizacao (is_running = true)
   - Registra inicio em log

2. **Determinar Contas para Sincronizar**
   - Se `accountType` = null: sincroniza Pro e Atacadista
   - Se especificado: sincroniza apenas aquela conta

3. **Para Cada Conta**
   - Obtem pagina inicial do cache (ou comeca em 1)
   - Busca pedidos do Bling (paginado)
   - Processa cada pedido individualmente
   - Salva proxima pagina no cache

4. **Processamento de Pedido** (metodo `processOrder`)
   - **Pedido Novo**: Sempre processa
   - **Pedido Existente**:
     - **Nivel 1**: Verifica idade (skip se muito antigo em sync incremental)
     - **Nivel 2**: Verifica hash leve no cache (skip se identico)
     - **Nivel 3**: Verifica hash no banco (skip se identico)
     - **Nivel 4**: Busca dados completos e gera hash abrangente
     - **Nivel 5**: Compara hash abrangente (skip se identico)
   
5. **Salvar Pedido**
   - Cria/atualiza OrderSummary
   - Calcula e salva hash de dados
   - Cria OrderStatus se nao existir
   - Registra em OrderHistory

6. **Disparar Eventos**
   - Broadcast OrderCreated ou OrderUpdated
   - Dispara OrderSynced Event (triggera outros jobs)

7. **Finalizacao**
   - Atualiza status de sincronizacao (is_running = false)
   - Registra resumo em log

**Otimizacoes**:

#### Sistema de Hash Multi-Nivel

```php
// Hash leve (apenas dados do endpoint de lista)
$lightHash = md5(json_encode([
    'id', 'numero', 'total', 'situacao', 'notaFiscal_id'
]));

// Hash abrangente (inclui invoice e tracking)
$fullHash = md5(json_encode([
    'order' => [...],
    'invoice' => [...],
    'tracking' => [...]
]));
```

#### Cache de Hashes

```php
// Cachear hash por 2 horas
Cache::put("bling_order_hash_{$blingId}", $hash, now()->addHours(2));
```

#### Paginacao Controlada

```php
// Processar maximo de 10 paginas por execucao (1000 pedidos)
// Evita timeout em contas com muitos pedidos
$maxPagesPerExecution = 10;
```

**Exemplo de Uso**:

```php
// Sincronizacao completa de todas as contas
BlingSyncJob::dispatch(
    forceUpdate: false,
    accountType: null,
    incrementalSync: true,
    maxPagesPerExecution: 10
);

// Sincronizacao forcada apenas do Pro
BlingSyncJob::dispatch(
    forceUpdate: true,
    accountType: 'pro',
    incrementalSync: false,
    maxPagesPerExecution: 0
);
```

### SyncInvoiceJob

**Arquivo**: `app/Jobs/SyncInvoiceJob.php`

**Descricao**: Sincroniza nota fiscal de um pedido especifico.

**Fila**: `invoices`

**Parametros**:
- `orderSummary` (OrderSummary): Pedido para sincronizar nota fiscal

**Configuracao**:
```php
public int $timeout = 300; // 5 minutos
public int $tries = 3;
public array $backoff = [30, 60, 120]; // 30s, 1min, 2min
```

**Fluxo de Execucao**:

1. Verifica se pedido tem ID de nota fiscal
2. Busca dados completos da nota fiscal no Bling
3. Mapeia dados para model Invoice
4. Cria/atualiza registro de invoice
5. Atualiza timestamp do pedido
6. Registra em OrderHistory
7. Dispara ExtractTrackingJob se houver volumes

**Exemplo de Uso**:

```php
SyncInvoiceJob::dispatch($orderSummary)
    ->onQueue('invoices');
```

### ExtractTrackingJob

**Arquivo**: `app/Jobs/ExtractTrackingJob.php`

**Descricao**: Extrai dados de rastreamento de nota fiscal.

**Fila**: `tracking`

**Parametros**:
- `invoice` (Invoice): Nota fiscal para extrair rastreamento

**Configuracao**:
```php
public int $timeout = 300; // 5 minutos
public int $tries = 3;
public array $backoff = [30, 60, 120]; // 30s, 1min, 2min
```

**Fluxo de Execucao**:

1. Verifica se invoice tem volumes de transporte
2. Para cada volume:
   - Busca dados completos do objeto logistico no Bling
   - Extrai codigo de rastreamento
   - Identifica transportadora
   - Mapeia dados para model Tracking
   - Cria/atualiza registro de tracking
3. Atualiza OrderSummary com tracking_code e logistics_provider
4. Registra em OrderHistory

**Exemplo de Uso**:

```php
ExtractTrackingJob::dispatch($invoice)
    ->onQueue('tracking');
```

### RefreshTrackingsJob

**Arquivo**: `app/Jobs/RefreshTrackingsJob.php`

**Descricao**: Job agendado que atualiza periodicamente os dados de rastreamento de pedidos recentes.

**Fila**: `default`

**Configuracao**:
```php
public int $timeout = 300; // 5 minutos
public int $tries = 3;
public array $backoff = [60, 300]; // 1min, 5min
```

**Agendamento**: Executa a cada 1 hora (hourly)

**Fluxo de Execucao**:

1. Busca pedidos dos ultimos 30 dias com:
   - `tracking_code` nao nulo
   - Invoice associada
   - Nao cancelados (situacao_id != 12)
2. Para cada pedido, dispara `ExtractTrackingJob` na fila tracking
3. Registra estatisticas (total encontrado, enfileirado, pulado)
4. Logs em `storage/logs/tracking/tracking-{date}.log`

**Prevencao de Duplicatas**:
- Verifica se job ja existe na fila antes de enfileirar
- `ExtractTrackingJob` possui cache de 1 hora

**Exemplo de Uso**:

```php
// Executado automaticamente pelo Laravel Scheduler
// Configurado em ScheduleServiceProvider
```

**Monitoramento**:

```bash
# Verificar agendamento
php artisan schedule:list | grep tracking

# Ver logs
tail -f storage/logs/tracking/tracking-$(date +%Y-%m-%d).log

# Verificar fila
php artisan tinker --execute="echo DB::table('jobs')->where('queue', 'tracking')->count();"
```

### SyncSellerJob

**Arquivo**: `app/Jobs/SyncSellerJob.php`

**Descricao**: Sincroniza vendedor de um pedido do WooCommerce.

**Fila**: `sellers`

**Parametros**:
- `orderSummary` (OrderSummary): Pedido para sincronizar vendedor

**Configuracao**:
```php
public int $timeout = 300; // 5 minutos
public int $tries = 2;
public array $backoff = [60, 120]; // 1min, 2min
```

**Fluxo de Execucao**:

1. Verifica se pedido e de Pro ou Atacadista (apenas esses tem vendedores)
2. Extrai WooCommerce Order ID de numero_loja
3. Busca dados do vendedor via WooCommerceService
4. Cria/atualiza Seller
5. Associa vendedor ao pedido (tabela pivot)

**Exemplo de Uso**:

```php
SyncSellerJob::dispatch($orderSummary)
    ->onQueue('sellers');
```

### RefreshBlingTokensJob

**Arquivo**: `app/Jobs/RefreshBlingTokensJob.php`

**Descricao**: Faz refresh automatico dos tokens OAuth do Bling.

**Fila**: `default`

**Configuracao**:
```php
public int $timeout = 120; // 2 minutos
public int $tries = 3;
```

**Fluxo de Execucao**:

1. Busca todos os tokens cadastrados
2. Para cada token:
   - Verifica se vai expirar em breve (menos de 1 hora)
   - Faz refresh via BlingOAuthService
   - Atualiza registro em bling_tokens
   - Registra em log

**Agendamento**:

```php
// routes/console.php
Schedule::job(new RefreshBlingTokensJob)->everyFourHours();
```

**Exemplo de Uso**:

```php
// Execucao manual
RefreshBlingTokensJob::dispatchSync();

// Execucao agendada (automatica)
// Configurado em routes/console.php
```

### SendHourlyStatsJob

**Arquivo**: `app/Jobs/SendHourlyStatsJob.php`

**Descricao**: Envia estatisticas horarias do sistema.

**Fila**: `default`

**Configuracao**:
```php
public int $timeout = 60; // 1 minuto
public int $tries = 2;
```

**Fluxo de Execucao**:

1. Coleta metricas da ultima hora:
   - Novos pedidos
   - Pedidos atualizados
   - Notas fiscais sincronizadas
   - Tracking extraido
   - Erros ocorridos
2. Formata dados
3. Envia via broadcasting ou log

**Agendamento**:

```php
// routes/console.php
Schedule::job(new SendHourlyStatsJob)->hourly();
```

## Estrategia de Retry e Fallback

### Backoff Exponencial

Jobs usam backoff exponencial para retry:

```php
public array $backoff = [60, 300, 600, 1800];
// Tentativa 1: Imediata
// Tentativa 2: Apos 60 segundos
// Tentativa 3: Apos 300 segundos (5 minutos)
// Tentativa 4: Apos 600 segundos (10 minutos)
// Tentativa 5: Apos 1800 segundos (30 minutos)
```

### Failed Jobs

Jobs que falham apos todas as tentativas sao movidos para tabela `failed_jobs`:

```sql
SELECT * FROM failed_jobs ORDER BY failed_at DESC;
```

### Retry de Jobs Falhados

```php
// Retry de um job especifico
php artisan queue:retry {job-id}

// Retry de todos os jobs falhados
php artisan queue:retry all

// Limpar jobs falhados
php artisan queue:flush
```

### Tratamento de Erros Especificos

#### Rate Limit (429)

```php
if ($status === 429) {
    // Nao fazer retry imediato
    // Deixar queue fazer retry com backoff
    throw new Exception("Rate limit exceeded, job will retry later");
}
```

#### Not Found (404)

```php
if ($status === 404) {
    // Nao fazer retry, recurso nao existe
    Log::warning("Resource not found");
    throw new Exception("Resource not found: {$endpoint}");
}
```

#### Unauthorized (401)

```php
if ($status === 401) {
    // Nao fazer retry, problema de token
    Log::error("Authentication failed");
    throw new Exception("Authentication failed");
}
```

#### Server Error (5xx)

```php
if ($status >= 500) {
    // Fazer retry com backoff
    $backoffDelay = min(pow(2, $attempt - 1), 8);
    sleep($backoffDelay);
    continue;
}
```

## Monitoramento de Jobs

### Horizon (Opcional)

Laravel Horizon pode ser instalado para monitoramento visual:

```bash
composer require laravel/horizon
php artisan horizon:install
php artisan horizon
```

Acesse: `http://localhost:8000/horizon`

### Queue Monitor (Artisan)

```bash
# Ver jobs na fila
php artisan queue:monitor

# Ver workers ativos
php artisan queue:work --verbose

# Limpar fila
php artisan queue:clear

# Contar jobs por fila
php artisan queue:work --stop-when-empty
```

### Logs

Jobs registram atividades em logs especificos:

```bash
# Log de sincronizacao Bling
tail -f storage/logs/bling-sync.log

# Log geral do Laravel
tail -f storage/logs/laravel.log
```

## Performance e Otimizacao

### Rate Limiting Conservador

O sistema usa rate limiting muito conservador (0.5 req/s por worker) para garantir confiabilidade:

- **Vantagens**: Zero bloqueios, 100% de confiabilidade
- **Desvantagens**: Sincronizacao mais lenta

**Calculo**:
```
Workers: 4 (1 BlingSyncJob + 2 SyncInvoiceJob + 1 ExtractTrackingJob)
Rate por worker: 0.5 req/s
Total: 4 × 0.5 = 2 req/s (67% do limite de 3 req/s)
Margem de seguranca: 33%
```

### Paginacao Controlada

BlingSyncJob processa no maximo 10 paginas (1000 pedidos) por execucao:

- Evita timeout em contas grandes
- Permite processamento incremental
- Proxima execucao continua de onde parou

### Cache de Hashes

Sistema usa cache para evitar processamento desnecessario:

- Cache de 2 horas para hashes de pedidos
- Skip automatico se hash identico
- Invalida cache quando pedido e atualizado

### Processamento Paralelo

4 workers processan dados simultaneamente:

```
Worker 1: BlingSyncJob (busca pedidos)
    |
    └─> Dispara eventos
            |
            ├─> Worker 2,3: SyncInvoiceJob (busca notas fiscais)
            ├─> Worker 2,3: ExtractTrackingJob (busca rastreamento)
            └─> Worker 4: SyncSellerJob (busca vendedores)
```

## Troubleshooting

### Jobs Nao Estao Executando

1. Verificar se workers estao rodando:
```bash
ps aux | grep "queue:work"
```

2. Verificar configuracao de fila no `.env`:
```env
QUEUE_CONNECTION=database
```

3. Reiniciar workers:
```bash
php artisan queue:restart
```

### Jobs Falhando Constantemente

1. Verificar logs:
```bash
tail -f storage/logs/laravel.log
```

2. Verificar failed_jobs:
```sql
SELECT * FROM failed_jobs ORDER BY failed_at DESC LIMIT 10;
```

3. Inspecionar payload do job:
```sql
SELECT payload FROM failed_jobs WHERE id = {id};
```

### Performance Lenta

1. Aumentar numero de workers (se infra permitir)
2. Ajustar rate limiting (com cuidado)
3. Usar cache mais agressivo
4. Otimizar queries de banco de dados

### Memory Leak

1. Usar `--max-jobs` e `--max-time`:
```bash
php artisan queue:work --max-jobs=1000 --max-time=3600
```

2. Monitorar uso de memoria:
```bash
watch -n 1 'ps aux | grep queue:work'
```

3. Configurar supervisor para reiniciar workers periodicamente

