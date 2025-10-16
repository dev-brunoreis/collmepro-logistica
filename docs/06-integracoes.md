# Integracoes Externas

## Visao Geral

O sistema integra-se com diversas APIs externas para importar pedidos, notas fiscais e dados de rastreamento. Todas as integracoes utilizam tratamento robusto de erros, retry logic e rate limiting.

## Bling API v3

### Informacoes Gerais

- **URL Base**: `https://api.bling.com.br/Api/v3`
- **Autenticacao**: OAuth 2.0 (Client Credentials)
- **Versao**: v3
- **Documentacao**: https://developer.bling.com.br/

### Rate Limiting

A API do Bling possui limites rigorosos:

- **3 requisicoes por segundo** (por conta)
- **120.000 requisicoes por dia**
- **Bloqueio de IP**: 600 requisicoes em 10 segundos = bloqueio de 10 minutos

### Implementacao de Rate Limiting

O sistema implementa rate limiting conservador para evitar bloqueios:

```php
protected function applyRateLimit(): void
{
    // 2000ms = 0.5 req/s por worker
    // Com 4 workers: 4 × 0.5 = 2 req/s total (67% do limite de 3 req/s)
    usleep(2000000); // 2 segundos
}
```

Esta estrategia garante:
- 33% de margem de seguranca abaixo do limite
- Zero erros 429 (rate limit exceeded)
- Nenhum risco de bloqueio de IP
- Sincronizacao lenta mas 100% confiavel

### OAuth 2.0 - Autenticacao

#### Fluxo de Autenticacao

1. **Obter Authorization Code**: Usuario autoriza aplicacao no Bling
2. **Trocar Code por Token**: Sistema troca code por access_token e refresh_token
3. **Usar Access Token**: Requisicoes usam access_token no header
4. **Refresh Token**: Antes de expirar (21 dias), sistema faz refresh automatico

#### BlingOAuthService

Arquivo: `app/Services/BlingOAuthService.php`

```php
// Obter URL de autorizacao
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

// Trocar code por token
public function exchangeCodeForToken(string $code, string $clientId, string $clientSecret): array
{
    $response = Http::asForm()->post('https://www.bling.com.br/Api/v3/oauth/token', [
        'grant_type' => 'authorization_code',
        'code' => $code,
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
    ]);
    
    return $response->json();
}

// Fazer refresh do token
public function refreshToken(string $refreshToken, string $clientId, string $clientSecret): array
{
    $response = Http::asForm()->post('https://www.bling.com.br/Api/v3/oauth/token', [
        'grant_type' => 'refresh_token',
        'refresh_token' => $refreshToken,
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
    ]);
    
    return $response->json();
}
```

#### Armazenamento de Tokens

Tokens sao armazenados na tabela `bling_tokens`:

```php
BlingToken::updateOrCreate(
    ['account_type' => $accountType],
    [
        'access_token' => $tokenData['access_token'],
        'refresh_token' => $tokenData['refresh_token'],
        'expires_at' => now()->addSeconds($tokenData['expires_in']),
        'scope' => $tokenData['scope']
    ]
);
```

#### Refresh Automatico

O job `RefreshBlingTokensJob` e executado a cada 4 horas via Laravel Scheduler:

```php
// routes/console.php
Schedule::job(new RefreshBlingTokensJob)->everyFourHours();
```

### Recursos da API Utilizados

#### 1. Pedidos de Venda

**Endpoint**: `GET /pedidos/vendas`

**Parametros**:
- `pagina`: Numero da pagina (default: 1)
- `limite`: Itens por pagina (max: 100)
- `dataAlteracaoInicial`: Data inicial de alteracao (formato: Y-m-d H:i:s)
- `dataAlteracaoFinal`: Data final de alteracao

**Uso**:
```php
$response = $this->makeRequest('GET', '/pedidos/vendas', [
    'pagina' => 1,
    'limite' => 100,
    'dataAlteracaoInicial' => '2025-01-01 00:00:00'
]);
```

**Resposta**:
```json
{
    "data": [
        {
            "id": 123456,
            "numero": "12345",
            "numeroLoja": "10001",
            "data": "2025-01-15",
            "total": 150.50,
            "situacao": {
                "id": 6,
                "valor": 2
            },
            "contato": {
                "id": 789,
                "nome": "Cliente Exemplo",
                "numeroDocumento": "12345678900"
            },
            "loja": {
                "id": 204303730
            },
            "notaFiscal": {
                "id": 98765
            }
        }
    ]
}
```

#### 2. Detalhes do Pedido

**Endpoint**: `GET /pedidos/vendas/{id}`

**Uso**:
```php
$response = $this->makeRequest('GET', "/pedidos/vendas/{$orderId}");
```

**Resposta**: Inclui dados completos do pedido, incluindo itens, parcelas, transporte.

#### 3. Notas Fiscais (NFe)

**Endpoint**: `GET /nfe/{id}`

**Uso**:
```php
$response = $this->makeRequest('GET', "/nfe/{$invoiceId}");
```

**Resposta**:
```json
{
    "data": {
        "id": 98765,
        "tipo": 1,
        "situacao": 5,
        "numero": "000123",
        "dataEmissao": "2025-01-15 10:30:00",
        "chaveAcesso": "35250112345678901234550010001230001234567890",
        "valorNota": 150.50,
        "linkDanfe": "https://...",
        "linkPDF": "https://...",
        "xml": "<nfeProc>...</nfeProc>",
        "transporte": {
            "volumes": [
                {
                    "id": 56789,
                    "servico": {
                        "id": 123,
                        "nome": "PAC"
                    }
                }
            ]
        }
    }
}
```

#### 4. Objetos Logisticos (Rastreamento)

**Endpoint**: `GET /logisticas/objetos/{volumeId}`

**Uso**:
```php
$response = $this->makeRequest('GET', "/logisticas/objetos/{$volumeId}");
```

**Resposta**:
```json
{
    "data": {
        "id": 56789,
        "rastreamento": {
            "codigo": "AA123456789BR",
            "descricao": "Correios - PAC",
            "situacao": "Em transito",
            "url": "https://rastreamento.correios.com.br/...",
            "ultimaAlteracao": "2025-01-16 14:30:00"
        },
        "servico": {
            "id": 123,
            "nome": "PAC"
        },
        "dimensao": {
            "peso": 1.5,
            "altura": 20,
            "largura": 30,
            "comprimento": 40
        },
        "origem": "Sao Paulo - SP",
        "destino": "Rio de Janeiro - RJ"
    }
}
```

### BlingService (Classe Abstrata)

Arquivo: `app/Services/BlingService.php`

Classe base para integracoes com Bling que implementa toda a logica comum.

#### Metodos Principais

```php
// Obter token valido (com refresh automatico)
protected function getValidToken(): string

// Fazer requisicao com retry logic
protected function makeRequest(string $method, string $endpoint, array $data = []): array

// Aplicar rate limiting
protected function applyRateLimit(): void

// Sincronizar pedidos
public function syncOrders(): array

// Obter todos os pedidos com paginacao
public function getAllOrders(array $filters = [], bool $incrementalSync = true): array

// Obter dados completos de pedido
public function getCompleteOrderData(int $orderId): ?array

// Obter dados completos de nota fiscal
public function getCompleteInvoiceData(int $invoiceId): ?array

// Obter dados completos de rastreamento
public function getCompleteTrackingData(int $volumeId): ?array
```

#### Retry Logic

O sistema implementa retry automatico com backoff exponencial:

```php
$maxAttempts = 3;
$attempt = 0;

while ($attempt < $maxAttempts) {
    try {
        // Fazer requisicao
        return $response->json();
    } catch (ConnectionException $e) {
        $backoffDelay = min(pow(2, $attempt - 1), 8); // Max 8 segundos
        sleep($backoffDelay);
        $attempt++;
    }
}
```

#### Tratamento de Erros HTTP

- **404 Not Found**: Nao faz retry, lanca excecao
- **429 Rate Limit**: Nao faz retry imediato, deixa queue fazer retry depois
- **401 Unauthorized**: Nao faz retry, lanca excecao (problema de token)
- **5xx Server Error**: Faz retry com backoff exponencial
- **Connection Timeout**: Faz retry com backoff exponencial

### Implementacoes Concretas

#### CollmeProBlingService

Arquivo: `app/Services/CollmeProBlingService.php`

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

#### CollmeAtacadistaBlingService

Arquivo: `app/Services/CollmeAtacadistaBlingService.php`

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

### Mapeamento de Lojas

#### SystemMappingService

Arquivo: `app/Services/SystemMappingService.php`

Mapeia IDs de lojas do Bling para sistemas internos:

```php
public static function mapLojaIdToSource(int $lojaId): string
{
    return match($lojaId) {
        204303730 => 'pro',           // Collmepro Pro
        204651922 => 'atacadista',    // Collmepro Atacadista
        default => 'bling'            // Outras lojas
    };
}

public static function mapSourceToLojaId(string $source): ?int
{
    return match($source) {
        'pro' => 204303730,
        'atacadista' => 204651922,
        default => null
    };
}
```

## WooCommerce API (Deprecated)

Originalmente planejado para importacao direta de pedidos, mas substituido por integracao via Bling. Mantido apenas para busca de vendedores.

### WooCommerceService

Arquivo: `app/Services/WooCommerceService.php`

```php
// Obter vendedor de um pedido
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

## CarrierService (Mapeamento de Transportadoras)

Arquivo: `app/Services/CarrierService.php`

Mapeia nomes de servicos de transporte para nomes exibidos:

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
    
    // Outras transportadoras
    return $serviceType;
}
```

## APIs de Rastreamento

### Correios

O rastreamento dos Correios e obtido atraves da API do Bling, que ja retorna o codigo de rastreamento e URL.

**URL de Rastreamento**: `https://rastreamento.correios.com.br/app/index.php`

### Total Express

Similar ao Correios, obtido via API do Bling.

### Melhor Envio

Similar ao Correios, obtido via API do Bling.

## Configuracao

### Arquivo .env

```env
# Bling - Collmepro Pro
BLING_PRO_CLIENT_ID=your-client-id
BLING_PRO_CLIENT_SECRET=your-client-secret
BLING_PRO_CODE=authorization-code

# Bling - Collmepro Atacadista
BLING_ATACADISTA_CLIENT_ID=your-client-id
BLING_ATACADISTA_CLIENT_SECRET=your-client-secret
BLING_ATACADISTA_CODE=authorization-code

# Bling - Geral
BLING_BASE_URL=https://api.bling.com.br/Api/v3
BLING_TIMEOUT=30
BLING_RATE_LIMIT=0.5
BLING_LOGGING_ENABLED=true
```

### Arquivo config/bling.php

```php
return [
    'api' => [
        'base_url' => env('BLING_BASE_URL', 'https://api.bling.com.br/Api/v3'),
        'timeout' => env('BLING_TIMEOUT', 30),
    ],
    
    'rate_limit' => [
        'requests_per_second' => env('BLING_RATE_LIMIT', 0.5),
    ],
    
    'defaults' => [
        'limit_per_page' => 100,
        'max_pages' => 10,
        'invoice_status' => 5, // Autorizada
        'invoice_type' => 'S', // Saida
    ],
    
    'logging' => [
        'enabled' => env('BLING_LOGGING_ENABLED', true),
        'log_requests' => env('BLING_LOG_REQUESTS', false),
        'log_responses' => env('BLING_LOG_RESPONSES', false),
    ],
];
```

## Logs de Integracao

### Canal de Log Especifico

Arquivo: `config/logging.php`

```php
'channels' => [
    'bling_sync' => [
        'driver' => 'daily',
        'path' => storage_path('logs/bling-sync.log'),
        'level' => 'debug',
        'days' => 14,
    ],
],
```

### Uso

```php
Log::channel('bling_sync')->info('Starting sync', [
    'account' => 'pro',
    'date' => now()
]);
```

## Monitoramento e Troubleshooting

### Verificar Status de Tokens

```php
$tokens = BlingToken::all();
foreach ($tokens as $token) {
    echo "Account: {$token->account_type}\n";
    echo "Expires: {$token->expires_at}\n";
    echo "Valid: " . ($token->expires_at->isFuture() ? 'Yes' : 'No') . "\n\n";
}
```

### Forcar Refresh de Tokens

```bash
php artisan tinker
```

```php
\App\Jobs\RefreshBlingTokensJob::dispatchSync();
```

### Verificar Logs de Sync

```bash
tail -f storage/logs/bling-sync.log
```

### Limpar Cache de Hashes

```php
$service = app(\App\Services\CollmeProBlingService::class);
$service->clearAllOrderCache();
```

## Boas Praticas

### Sempre Usar Rate Limiting

```php
// CORRETO
$response = $this->makeRequest('GET', '/endpoint');
$this->applyRateLimit();

// INCORRETO - pode causar bloqueio
$response = $this->makeRequest('GET', '/endpoint');
// Faz outra requisicao imediatamente
```

### Tratar Erros Apropriadamente

```php
// CORRETO
try {
    $data = $this->makeRequest('GET', '/endpoint');
} catch (\Exception $e) {
    Log::error('API request failed', [
        'error' => $e->getMessage(),
        'endpoint' => '/endpoint'
    ]);
    throw $e;
}

// INCORRETO - engole erros
try {
    $data = $this->makeRequest('GET', '/endpoint');
} catch (\Exception $e) {
    // Silencioso
}
```

### Usar Sync Incremental

```php
// CORRETO - apenas ultimos 30 dias
$orders = $service->getAllOrders([], incrementalSync: true);

// INCORRETO - todos os pedidos (lento)
$orders = $service->getAllOrders([], incrementalSync: false);
```

