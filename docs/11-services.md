# Services e Logica de Negocio

## Visao Geral

Services encapsulam toda a logica de negocio do sistema, mantendo controllers finos e focados em requisicoes HTTP. Cada service e responsavel por um dominio especifico da aplicacao.

## Estrutura de Services

```
app/Services/
├── BlingOAuthService.php              # Gerenciamento OAuth Bling
├── BlingService.php                   # Classe abstrata base
├── CarrierService.php                 # Mapeamento de transportadoras
├── CollmeAtacadistaBlingService.php   # Integracao Atacadista
├── CollmeProBlingService.php          # Integracao Pro
├── OrderHistoryService.php            # Historico de pedidos
├── SystemMappingService.php           # Mapeamentos de sistema
└── WooCommerceService.php            # Integracao WooCommerce
```

## BlingService

**Arquivo**: `app/Services/BlingService.php`

### Descricao
Classe abstrata base que implementa toda a logica de integracao com a API do Bling. Classes concretas (CollmeProBlingService, CollmeAtacadistaBlingService) estendem esta classe.

### Metodos Principais

#### getValidToken()
Obtem token OAuth valido, fazendo refresh automatico se necessario.

```php
protected function getValidToken(): string
{
    return $this->oauthService->getValidToken(
        $this->getAccountType(),
        $this->getClientId(),
        $this->getClientSecret()
    );
}
```

#### makeRequest()
Faz requisicao HTTP para API do Bling com retry logic.

**Parametros**:
- `method`: HTTP method (GET, POST, PUT, DELETE)
- `endpoint`: Endpoint da API
- `data`: Dados a enviar (array)

**Retorno**: Array com resposta da API

**Retry Logic**:
- Maximo 3 tentativas
- Backoff exponencial para erros de conexao
- Nao faz retry para 404, 401, 429

```php
protected function makeRequest(string $method, string $endpoint, array $data = []): array
{
    $maxAttempts = 3;
    // ... implementacao com retry
}
```

#### applyRateLimit()
Aplica rate limiting conservador para evitar bloqueios.

```php
protected function applyRateLimit(): void
{
    usleep(2000000); // 2 segundos = 0.5 req/s
}
```

#### syncOrders()
Sincroniza pedidos da conta Bling.

**Retorno**:
```php
[
    'account' => 'pro',
    'total_orders' => 100,
    'processed' => 95,
    'created' => 10,
    'updated' => 85,
    'skipped' => 0,
    'failed' => 5,
]
```

#### getAllOrders()
Obtem todos os pedidos com paginacao.

**Parametros**:
- `filters`: Filtros adicionais (array)
- `incrementalSync`: Se true, busca apenas ultimos 30 dias
- `maxPages`: Maximo de paginas (0 = ilimitado)
- `startPage`: Pagina inicial

**Retorno**: Array de pedidos

#### getCompleteOrderData()
Obtem dados completos de um pedido.

**Parametros**:
- `orderId`: ID do pedido no Bling

**Retorno**: Array com dados completos ou null

#### getCompleteInvoiceData()
Obtem dados completos de uma nota fiscal.

**Parametros**:
- `invoiceId`: ID da nota fiscal no Bling

**Retorno**: Array com dados completos ou null

#### getCompleteTrackingData()
Obtem dados completos de rastreamento de um volume.

**Parametros**:
- `volumeId`: ID do volume no Bling

**Retorno**: Array com dados completos ou null

### Metodos de Hash

#### generateLightweightHash()
Gera hash MD5 leve para deteccao rapida de mudancas.

```php
public function generateLightweightHash(array $blingOrder): string
{
    $criticalData = [
        'id' => $blingOrder['id'],
        'numero' => $blingOrder['numero'],
        'total' => $blingOrder['total'] ?? 0,
        'situacao_id' => $blingOrder['situacao']['id'] ?? 1,
        'notaFiscal_id' => $blingOrder['notaFiscal']['id'] ?? null,
    ];
    
    return md5(json_encode($criticalData));
}
```

#### generateOrderHash()
Gera hash MD5 abrangente incluindo invoice e tracking.

```php
public function generateOrderHash(array $completeOrderData): string
{
    $criticalData = [
        'order' => [...],
        'invoice' => [...],
        'tracking' => [...],
    ];
    
    return md5(json_encode($criticalData));
}
```

### Cache de Hashes

#### getCachedOrderHash()
Obtem hash do cache.

#### setCachedOrderHash()
Armazena hash no cache (TTL: 2 horas).

#### invalidateOrderCache()
Invalida cache de um pedido especifico.

#### clearAllOrderCache()
Limpa todos os caches de pedidos.

## BlingOAuthService

**Arquivo**: `app/Services/BlingOAuthService.php`

### Descricao
Gerencia autenticacao OAuth 2.0 com a API do Bling.

### Metodos Principais

#### getAuthorizationUrl()
Gera URL de autorizacao para obter authorization code.

```php
public function getAuthorizationUrl(string $accountType, string $clientId): string
{
    $state = Str::random(40);
    Cache::put("bling_oauth_state_{$accountType}", $state, now()->addMinutes(10));
    
    return "https://www.bling.com.br/Api/v3/oauth/authorize?" . http_build_query([
        'response_type' => 'code',
        'client_id' => $clientId,
        'state' => $state,
    ]);
}
```

#### exchangeCodeForToken()
Troca authorization code por access token e refresh token.

**Parametros**:
- `code`: Authorization code
- `clientId`: Client ID da aplicacao
- `clientSecret`: Client Secret da aplicacao

**Retorno**:
```php
[
    'access_token' => '...',
    'refresh_token' => '...',
    'expires_in' => 1814400, // 21 dias em segundos
    'scope' => '...',
]
```

#### refreshToken()
Faz refresh de um token expirado.

**Parametros**:
- `refreshToken`: Refresh token
- `clientId`: Client ID
- `clientSecret`: Client Secret

**Retorno**: Mesmo formato de exchangeCodeForToken()

#### getValidToken()
Obtem token valido, fazendo refresh se necessario.

**Parametros**:
- `accountType`: Tipo de conta ('pro', 'atacadista')
- `clientId`: Client ID
- `clientSecret`: Client Secret

**Retorno**: Access token valido (string)

**Fluxo**:
1. Busca token no banco de dados
2. Verifica se vai expirar em breve (1 hora)
3. Se sim, faz refresh
4. Retorna token valido

## CollmeProBlingService

**Arquivo**: `app/Services/CollmeProBlingService.php`

### Descricao
Implementacao concreta para integracao com conta Collmepro Pro.

```php
class CollmeProBlingService extends BlingService
{
    protected function getAccountType(): string
    {
        return 'pro';
    }

    protected function getClientId(): string
    {
        return config('services.bling.pro.client_id');
    }

    protected function getClientSecret(): string
    {
        return config('services.bling.pro.client_secret');
    }
}
```

## CollmeAtacadistaBlingService

**Arquivo**: `app/Services/CollmeAtacadistaBlingService.php`

### Descricao
Implementacao concreta para integracao com conta Collmepro Atacadista.

```php
class CollmeAtacadistaBlingService extends BlingService
{
    protected function getAccountType(): string
    {
        return 'atacadista';
    }

    protected function getClientId(): string
    {
        return config('services.bling.atacadista.client_id');
    }

    protected function getClientSecret(): string
    {
        return config('services.bling.atacadista.client_secret');
    }
}
```

## CarrierService

**Arquivo**: `app/Services/CarrierService.php`

### Descricao
Service estatico para mapeamento de transportadoras e servicos de entrega.

### Metodo Principal

#### getCarrierDisplay()
Retorna nome formatado da transportadora.

```php
public static function getCarrierDisplay(?string $serviceType): string
{
    if (empty($serviceType)) {
        return 'N/A';
    }
    
    // Correios
    if (stripos($serviceType, 'PAC') !== false) {
        return 'Correios - PAC';
    }
    if (stripos($serviceType, 'SEDEX') !== false) {
        return 'Correios - SEDEX';
    }
    
    // Total Express
    if (stripos($serviceType, 'Total') !== false) {
        return 'Total Express';
    }
    
    // Melhor Envio
    if (stripos($serviceType, 'Melhor') !== false) {
        return 'Melhor Envio';
    }
    
    return $serviceType;
}
```

## OrderHistoryService

**Arquivo**: `app/Services/OrderHistoryService.php`

### Descricao
Gerencia historico de alteracoes em pedidos.

### Metodos Principais

#### recordOrderImported()
Registra importacao de pedido.

```php
public function recordOrderImported(OrderSummary $order, string $source, array $metadata = [], string $workerName = null): void
{
    OrderHistory::create([
        'order_summary_id' => $order->id,
        'user_id' => auth()->id(),
        'action' => 'order_imported',
        'description' => "Order imported from {$source}",
        'metadata' => $metadata,
        'worker_name' => $workerName,
    ]);
}
```

#### recordInvoiceSynced()
Registra sincronizacao de nota fiscal.

```php
public function recordInvoiceSynced(OrderSummary $order, array $metadata = []): void
{
    OrderHistory::create([
        'order_summary_id' => $order->id,
        'action' => 'invoice_synced',
        'description' => 'Invoice synced successfully',
        'metadata' => $metadata,
    ]);
}
```

#### recordTrackingFetched()
Registra obtencao de dados de rastreamento.

```php
public function recordTrackingFetched(OrderSummary $order, array $metadata = []): void
{
    OrderHistory::create([
        'order_summary_id' => $order->id,
        'action' => 'tracking_fetched',
        'description' => 'Tracking data fetched',
        'metadata' => $metadata,
    ]);
}
```

#### recordOrderSyncCompleted()
Registra conclusao de sincronizacao de pedido.

```php
public function recordOrderSyncCompleted(OrderSummary $order, array $changedFields, array $metadata = []): void
{
    OrderHistory::create([
        'order_summary_id' => $order->id,
        'action' => 'order_synced',
        'description' => 'Order synced: ' . implode(', ', $changedFields),
        'metadata' => array_merge($metadata, ['changed_fields' => $changedFields]),
    ]);
}
```

#### recordStatusChanged()
Registra mudanca de status de processamento.

```php
public function recordStatusChanged(OrderSummary $order, string $field, bool $oldValue, bool $newValue): void
{
    OrderHistory::create([
        'order_summary_id' => $order->id,
        'user_id' => auth()->id(),
        'action' => 'status_updated',
        'description' => "Status {$field} changed",
        'old_value' => json_encode([$field => $oldValue]),
        'new_value' => json_encode([$field => $newValue]),
    ]);
}
```

## SystemMappingService

**Arquivo**: `app/Services/SystemMappingService.php`

### Descricao
Service estatico para mapeamento entre IDs de lojas e sistemas.

### Metodos

#### mapLojaIdToSource()
Mapeia ID de loja do Bling para nome do sistema.

```php
public static function mapLojaIdToSource(int $lojaId): string
{
    return match($lojaId) {
        204303730 => 'pro',
        204651922 => 'atacadista',
        default => 'bling'
    };
}
```

#### mapSourceToLojaId()
Mapeia nome do sistema para ID de loja do Bling.

```php
public static function mapSourceToLojaId(string $source): ?int
{
    return match($source) {
        'pro' => 204303730,
        'atacadista' => 204651922,
        default => null
    };
}
```

#### getSystemDisplayName()
Retorna nome amigavel do sistema.

```php
public static function getSystemDisplayName(string $source): string
{
    return match($source) {
        'pro' => 'Collmepro Pro',
        'atacadista' => 'Collmepro Atacadista',
        'beauty' => 'Collme Beauty',
        default => ucfirst($source)
    };
}
```

## WooCommerceService

**Arquivo**: `app/Services/WooCommerceService.php`

### Descricao
Integra com API do WooCommerce (usado apenas para buscar vendedores).

### Metodos

#### getOrderSeller()
Busca vendedor de um pedido.

**Parametros**:
- `orderId`: ID do pedido no WooCommerce
- `source`: Sistema ('pro' ou 'atacadista')

**Retorno**:
```php
[
    'id' => 123,
    'name' => 'Nome do Vendedor',
    'email' => 'vendedor@example.com',
]
```

**Implementacao**:
```php
public function getOrderSeller(int $orderId, string $source): ?array
{
    $baseUrl = $this->getBaseUrl($source);
    $consumerKey = $this->getConsumerKey($source);
    $consumerSecret = $this->getConsumerSecret($source);
    
    $response = Http::withBasicAuth($consumerKey, $consumerSecret)
        ->get("{$baseUrl}/wp-json/wc/v3/orders/{$orderId}");
    
    if ($response->successful()) {
        $order = $response->json();
        return [
            'id' => $order['seller_id'] ?? null,
            'name' => $order['seller_name'] ?? null,
            'email' => $order['seller_email'] ?? null
        ];
    }
    
    return null;
}
```

## Uso dos Services

### Injecao de Dependencia

Services podem ser injetados via constructor ou metodo:

```php
class OrderController extends Controller
{
    public function __construct(
        protected BlingService $blingService,
        protected OrderHistoryService $historyService
    ) {}
    
    public function sync()
    {
        $this->blingService->syncOrders();
    }
}
```

### Resolucao via Service Container

```php
$proService = app(CollmeProBlingService::class);
$orders = $proService->getAllOrders();
```

### Helper Global app()

```php
$historyService = app(OrderHistoryService::class);
$historyService->recordOrderImported($order, 'bling');
```

## Testes

Services devem ter testes unitarios mockando dependencias externas:

```php
public function test_sync_orders_creates_new_orders()
{
    Http::fake([
        'api.bling.com.br/*' => Http::response([
            'data' => [
                ['id' => 1, 'numero' => '12345'],
            ]
        ])
    ]);
    
    $service = app(CollmeProBlingService::class);
    $result = $service->syncOrders();
    
    $this->assertEquals(1, $result['created']);
}
```

## Boas Praticas

### Single Responsibility
Cada service tem uma responsabilidade unica e bem definida.

### Dependency Injection
Sempre usar injecao de dependencia, nunca instanciar diretamente.

### Testabilidade
Services devem ser facilmente testaveis com mocks.

### Logging
Registrar eventos importantes para auditoria e debugging.

### Error Handling
Tratar erros apropriadamente e lancar excecoes descritivas.

