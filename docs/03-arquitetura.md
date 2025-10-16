# Arquitetura do Sistema

## Estrutura de Diretorios

```
collmepro-logistica/
├── app/
│   ├── Console/             # Comandos Artisan
│   │   └── Commands/
│   ├── Events/              # Eventos do sistema
│   ├── Http/
│   │   ├── Controllers/     # Controladores
│   │   │   ├── Auth/        # Autenticacao
│   │   │   ├── Settings/    # Configuracoes
│   │   │   ├── Controller.php
│   │   │   ├── DashboardController.php
│   │   │   ├── KanbanController.php
│   │   │   ├── OrderController.php
│   │   │   ├── PermissionController.php
│   │   │   ├── ProfileController.php
│   │   │   └── UserController.php
│   │   └── Middleware/      # Middleware customizado
│   ├── Jobs/                # Jobs de fila
│   │   ├── BlingSyncJob.php
│   │   ├── ExtractTrackingJob.php
│   │   ├── RefreshBlingTokensJob.php
│   │   ├── SendHourlyStatsJob.php
│   │   ├── SyncInvoiceJob.php
│   │   └── SyncSellerJob.php
│   ├── Listeners/           # Listeners de eventos
│   ├── Models/              # Models Eloquent
│   │   ├── BlingToken.php
│   │   ├── Invoice.php
│   │   ├── Order.php
│   │   ├── OrderData.php
│   │   ├── OrderHistory.php
│   │   ├── OrderStatus.php
│   │   ├── OrderStatusHistory.php
│   │   ├── OrderSummary.php
│   │   ├── Permission.php
│   │   ├── RolePermission.php
│   │   ├── Seller.php
│   │   ├── Tracking.php
│   │   └── User.php
│   ├── Providers/           # Service Providers
│   ├── Services/            # Logica de negocio
│   │   ├── BlingOAuthService.php
│   │   ├── BlingService.php
│   │   ├── CarrierService.php
│   │   ├── CollmeAtacadistaBlingService.php
│   │   ├── CollmeProBlingService.php
│   │   ├── OrderHistoryService.php
│   │   ├── SystemMappingService.php
│   │   └── WooCommerceService.php
│   └── Traits/              # Traits reutilizaveis
├── bootstrap/              # Bootstrap do Laravel
├── config/                 # Arquivos de configuracao
│   ├── bling.php          # Configuracoes do Bling
│   └── ...
├── database/
│   ├── factories/         # Model Factories
│   ├── migrations/        # Migrations
│   └── seeders/           # Seeders
├── docs/                  # Documentacao
├── public/                # Assets publicos
├── resources/
│   ├── css/              # Estilos CSS
│   ├── js/               # Frontend Vue.js
│   │   ├── actions/      # Acoes reutilizaveis
│   │   ├── components/   # Componentes Vue
│   │   ├── composables/  # Composables Vue
│   │   ├── helpers/      # Funcoes auxiliares
│   │   ├── layouts/      # Layouts
│   │   ├── lib/          # Bibliotecas
│   │   ├── pages/        # Paginas Inertia
│   │   │   ├── auth/
│   │   │   ├── Kanban/
│   │   │   ├── Orders/
│   │   │   ├── Permissions/
│   │   │   ├── Profile/
│   │   │   ├── settings/
│   │   │   ├── Users/
│   │   │   ├── Dashboard.vue
│   │   │   └── Welcome.vue
│   │   ├── routes/       # Rotas do Wayfinder
│   │   ├── types/        # TypeScript types
│   │   ├── wayfinder/    # Configuracao Wayfinder
│   │   ├── app.ts        # Entry point
│   │   ├── echo.ts       # Configuracao Echo
│   │   └── ssr.ts        # SSR entry point
│   └── views/            # Views Blade
├── routes/
│   ├── auth.php          # Rotas de autenticacao
│   ├── channels.php      # Canais de broadcasting
│   ├── console.php       # Rotas console/scheduler
│   ├── settings.php      # Rotas de configuracoes
│   └── web.php           # Rotas web principais
├── storage/              # Storage do Laravel
│   ├── app/
│   ├── framework/
│   └── logs/
├── tests/                # Testes
│   ├── Feature/         # Testes de integracao
│   └── Unit/            # Testes unitarios
├── .env                 # Variaveis de ambiente
├── artisan              # CLI do Laravel
├── composer.json        # Dependencias PHP
├── package.json         # Dependencias Node
└── vite.config.ts       # Configuracao Vite
```

## Camadas da Aplicacao

### 1. Camada de Apresentacao (Frontend)

#### Vue.js + Inertia.js
- **Responsabilidade**: Interface do usuario
- **Tecnologias**: Vue 3, TypeScript, Tailwind CSS
- **Comunicacao**: Inertia.js para requisicoes, Echo para WebSockets

#### Estrutura de Componentes
```
components/
├── ui/              # Componentes de UI base (botoes, inputs, etc)
├── layout/          # Componentes de layout (header, sidebar, etc)
└── features/        # Componentes especificos de funcionalidades
```

#### Layouts
- **AuthenticatedLayout**: Layout principal para usuarios autenticados
- **GuestLayout**: Layout para paginas publicas

### 2. Camada de Roteamento

#### Web Routes (routes/web.php)
- Rotas principais da aplicacao
- Middleware de autenticacao
- Middleware de permissoes
- Agrupamento por recurso

#### Exemplo de Estrutura:
```php
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');
    
    Route::middleware(['permission:orders.view'])->group(function () {
        Route::get('orders', [OrderController::class, 'index'])
            ->name('orders.index');
    });
});
```

### 3. Camada de Controle (Controllers)

#### Responsabilidades
- Receber requisicoes HTTP
- Validar dados de entrada
- Chamar services para logica de negocio
- Retornar respostas (Inertia ou JSON)

#### Controladores Principais
- **DashboardController**: Metricas e visao geral
- **OrderController**: Gestao de pedidos
- **KanbanController**: Painel Kanban
- **UserController**: Gestao de usuarios
- **PermissionController**: Gestao de permissoes

### 4. Camada de Servicos (Services)

#### BlingService (Abstract)
- Classe base para integracao com Bling
- Gerenciamento de tokens OAuth
- Rate limiting
- Retry logic
- Sincronizacao de pedidos, notas fiscais e rastreamento

#### CollmeProBlingService
- Implementacao especifica para Collmepro Pro
- Herda de BlingService

#### CollmeAtacadistaBlingService
- Implementacao especifica para Collmepro Atacadista
- Herda de BlingService

#### BlingOAuthService
- Gerenciamento de tokens OAuth 2.0
- Refresh automatico de tokens
- Armazenamento seguro em banco de dados

#### OrderHistoryService
- Rastreamento de mudancas em pedidos
- Registro de acoes de usuarios
- Auditoria completa

#### CarrierService
- Mapeamento de transportadoras
- Formatacao de nomes de servicos

#### SystemMappingService
- Mapeamento de IDs de lojas para sistemas
- Centralizacao de regras de negocio

### 5. Camada de Modelos (Models)

#### OrderSummary
- Sumario de pedidos importados
- Relacionamento com OrderData, Invoice, OrderStatus
- Scopes para filtros comuns

#### Invoice
- Notas fiscais (NFe)
- Relacionamento com OrderSummary e Tracking
- Armazenamento de dados XML e PDF

#### Tracking
- Dados de rastreamento
- Relacionamento com Invoice
- Historico de eventos de rastreamento

#### User
- Usuarios do sistema
- Roles (Admin, Manager, Sales, Developer)
- Relacionamento com RolePermission

#### OrderStatus
- Status de processamento de pedidos (picking, packing, picked_up)
- Rastreamento de usuario e timestamp

### 6. Camada de Jobs (Background Processing)

#### BlingSyncJob
- Sincronizacao de pedidos do Bling
- Processamento em lotes
- Deteccao de mudancas via hash
- Fila: `default`

#### SyncInvoiceJob
- Sincronizacao de notas fiscais
- Triggered por evento OrderSynced
- Fila: `invoices`

#### ExtractTrackingJob
- Extracao de dados de rastreamento
- Triggered por evento OrderSynced
- Fila: `tracking`

#### SyncSellerJob
- Sincronizacao de vendedores
- Triggered por evento OrderSynced
- Fila: `sellers`

#### RefreshBlingTokensJob
- Refresh automatico de tokens OAuth
- Agendado a cada 4 horas
- Fila: `default`

#### SendHourlyStatsJob
- Envio de estatisticas horarias
- Agendado a cada hora
- Fila: `default`

### 7. Camada de Eventos e Listeners

#### Eventos
- **OrderSynced**: Disparado apos sincronizacao de pedido
- **OrderCreated**: Disparado apos criacao de pedido
- **OrderUpdated**: Disparado apos atualizacao de pedido
- **BlingSyncStatusUpdated**: Status de sincronizacao do Bling

#### Broadcasting
- Todos os eventos sao transmitidos via WebSockets
- Canal: `orders`
- Driver: Laravel Reverb

## Padroes de Design Utilizados

### 1. Repository Pattern (Parcial)
Embora nao seja implementado de forma completa, os Services atuam como uma camada de abstraca entre Controllers e Models.

### 2. Service Layer Pattern
Toda logica de negocio esta encapsulada em Services, mantendo Controllers finos.

### 3. Event-Driven Architecture
O sistema utiliza eventos para desacoplar componentes e permitir processamento assincrono.

### 4. Strategy Pattern
BlingService usa classes concretas (CollmeProBlingService, CollmeAtacadistaBlingService) para implementar estrategias especificas.

### 5. Observer Pattern
Laravel Events/Listeners implementam o padrao Observer para notificacoes.

### 6. Factory Pattern
Model Factories para geracao de dados de teste.

### 7. Singleton Pattern
Services sao registrados como singletons no Service Container.

## Sistema de Filas e Workers

### Configuracao de Filas

O sistema utiliza 4 filas especializadas:

```php
[
    'invoices' => [
        'workers' => 2,
        'tries' => 3,
        'timeout' => 300
    ],
    'tracking' => [
        'workers' => 2,
        'tries' => 3,
        'timeout' => 300
    ],
    'sellers' => [
        'workers' => 1,
        'tries' => 2,
        'timeout' => 300
    ],
    'default' => [
        'workers' => 1,
        'tries' => 3,
        'timeout' => 300
    ]
]
```

### Fluxo de Processamento

```
BlingSyncJob (default queue)
    |
    ├─> Detecta novo pedido ou mudanca
    |
    ├─> Salva OrderSummary
    |
    └─> Dispara OrderSynced Event
            |
            ├─> SyncInvoiceJob (invoices queue)
            │       └─> Busca nota fiscal no Bling
            │           └─> Salva Invoice
            │               └─> Dispara ExtractTrackingJob
            │
            ├─> ExtractTrackingJob (tracking queue)
            │       └─> Extrai dados de rastreamento
            │           └─> Salva Tracking
            │
            └─> SyncSellerJob (sellers queue)
                    └─> Busca vendedor no WooCommerce
                        └─> Salva Seller e relacionamento
```

## Broadcasting e Eventos em Tempo Real

### Laravel Reverb (WebSockets)

O sistema utiliza Laravel Reverb para comunicacao em tempo real:

```
Backend Event → Reverb Server → Echo Client → Vue Component → UI Update
```

### Configuracao do Cliente (frontend)

```typescript
// echo.ts
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT,
    wssPort: import.meta.env.VITE_REVERB_PORT,
    forceTLS: false,
    enabledTransports: ['ws', 'wss'],
});
```

### Canais de Broadcasting

- **orders**: Atualizacoes de pedidos
- **sync-status**: Status de sincronizacao

### Exemplo de Uso no Frontend

```vue
<script setup>
import { onMounted, onUnmounted } from 'vue';

onMounted(() => {
    window.Echo.channel('orders')
        .listen('OrderCreated', (e) => {
            console.log('New order:', e.order);
        })
        .listen('OrderUpdated', (e) => {
            console.log('Order updated:', e.order);
        });
});

onUnmounted(() => {
    window.Echo.leaveChannel('orders');
});
</script>
```

## Sistema de Cache e Otimizacoes

### Estrategias de Cache

#### 1. Cache de Tokens OAuth
```php
// Cache de 21 dias (lifetime dos tokens)
Cache::put("bling_token_{$accountType}", $token, now()->addDays(21));
```

#### 2. Cache de Hash de Pedidos
```php
// Cache de 2 horas (para deteccao rapida de mudancas)
Cache::put("bling_order_hash_{$blingId}", $hash, now()->addHours(2));
```

#### 3. Cache de Status de Sync
```php
// Cache de 2 horas
Cache::put('bling_sync_status', $statusData, now()->addHours(2));
```

### Otimizacoes de Performance

#### 1. Eager Loading
```php
OrderSummary::with([
    'orderStatus',
    'invoice',
    'orderData'
])->get();
```

#### 2. Lazy Loading Condicional
```php
if ($order->relationLoaded('invoice')) {
    // Use cached relationship
}
```

#### 3. Indices de Banco de Dados
- Indices em colunas frequentemente consultadas
- Indices compostos para consultas complexas

#### 4. Paginacao
- Uso de paginacao em listagens grandes
- Limite de resultados por pagina

## Middleware de Autorizacao

### PermissionMiddleware

Verifica se o usuario tem permissao para acessar um recurso:

```php
Route::middleware(['permission:orders.view'])->group(function () {
    // Rotas protegidas
});
```

### Verificacao de Permissoes

```php
// No middleware
public function handle($request, Closure $next, $permission)
{
    if (!$request->user()->hasPermission($permission)) {
        abort(403, 'Unauthorized action.');
    }
    return $next($request);
}
```

## Logging

### Canais de Log

- **stack**: Log padrao (combina multiple canais)
- **single**: Arquivo unico
- **daily**: Rotacao diaria de logs
- **bling_sync**: Log especifico para sincronizacao Bling

### Uso

```php
// Log padrao
Log::info('Message', ['context' => 'data']);

// Log no canal especifico
Log::channel('bling_sync')->info('Bling sync started', [
    'account' => 'pro'
]);
```

## Tratamento de Erros

### Retry Logic em Jobs

```php
public $tries = 3;
public $backoff = [60, 300, 600]; // 1min, 5min, 10min
```

### Retry em Requisicoes API

```php
// BlingService implementa retry automatico
protected function makeRequest(string $method, string $endpoint, array $data = []): array
{
    $maxAttempts = 3;
    $attempt = 0;
    
    while ($attempt < $maxAttempts) {
        try {
            // Faz requisicao
        } catch (ConnectionException $e) {
            // Retry com backoff exponencial
            $backoffDelay = min(pow(2, $attempt - 1), 8);
            sleep($backoffDelay);
        }
    }
}
```

## Escalabilidade

### Horizontal Scaling

- Workers podem ser escalados horizontalmente
- Cache distribuido (Redis) para compartilhar estado
- Broadcasting via Reverb (suporta multiplas instancias)

### Vertical Scaling

- Otimizacao de queries
- Indices de banco de dados
- Cache agressivo para dados raramente alterados

