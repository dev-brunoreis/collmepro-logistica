# Modulos Funcionais

## Visao Geral

O sistema e organizado em modulos funcionais que atendem diferentes aspectos da operacao logistica. Cada modulo possui suas proprias telas, controllers, e logica de negocio.

## Dashboard

### Descricao
Painel principal que exibe metricas e visao geral do sistema.

### Funcionalidades
- Exibicao de metricas em tempo real
- Total de pedidos (filtrado por periodo)
- Pedidos concluidos
- Visualizacao de dados do Kanban
- Indicador de status de sincronizacao
- Carregamento assincrono de dados para melhor performance

### Controller
`App\Http\Controllers\DashboardController`

### Rotas
```php
GET /dashboard                    # Pagina principal
GET /dashboard/data              # Buscar dados assincronamente
GET /dashboard/api/sync-status   # Status de sincronizacao
```

### Metodos Principais

#### index()
Renderiza a pagina inicial do dashboard com estado vazio. Dados sao carregados assincronamente.

```php
public function index(): Response
{
    return Inertia::render('Dashboard', [
        'metrics' => ['total_orders' => 0, 'completed_orders' => 0],
        'ordersByStatus' => [],
        'recentActivity' => [],
        'kanbanData' => [],
    ]);
}
```

#### fetchDashboardData()
Busca dados do dashboard com filtros opcionais.

**Parametros**:
- `days_back`: Numero de dias para filtrar (default: 7)
- `load_all`: Carregar todos os pedidos (booleano)

**Resposta JSON**:
```json
{
    "metrics": {
        "total_orders": 150,
        "completed_orders": 120,
        "total_all": 5000,
        "loaded": 150
    },
    "ordersByStatus": {
        "pending": [...],
        "picking": [...],
        "packing": [...],
        "picked_up": [...],
        "completed": [...]
    },
    "recentActivity": [...],
    "kanbanData": {
        "pedidos_emitidos": [...],
        "faturamento": [...],
        "checkout": [...],
        "embalagem": [...],
        "etiqueta": [...],
        "coleta_aguardando": [...],
        "coleta_realizada": [...]
    }
}
```

#### getSyncStatus()
Retorna status atual da sincronizacao do Bling.

**Resposta JSON**:
```json
{
    "is_running": false,
    "last_sync_at": "2025-01-16T14:30:00.000000Z",
    "account_type": "all",
    "updated_at": "2025-01-16T14:35:00.000000Z"
}
```

### Metricas Calculadas

#### Total de Pedidos
Conta todos os pedidos nao cancelados no periodo selecionado.

#### Pedidos Concluidos
Conta pedidos onde `order_status.picked_up = true`.

### Organizacao por Status Kanban

O sistema organiza pedidos em 7 colunas:

1. **Pedidos Emitidos**: Pedidos sem nota fiscal
2. **Faturamento**: Com nota fiscal, aguardando separacao
3. **Checkout**: Em separacao (picking = true)
4. **Embalagem**: Em embalagem (packing = true)
5. **Etiqueta**: Embalado, sem codigo de rastreamento
6. **Coleta Aguardando**: Com rastreamento, aguardando coleta
7. **Coleta Realizada**: Coletado (picked_up = true)

## Gestao de Pedidos

### Descricao
Modulo completo para visualizacao e gestao de pedidos importados.

### Funcionalidades
- Listagem de pedidos com paginacao
- Filtros avancados (sistema, status, data, cliente)
- Busca por numero de pedido
- Visualizacao detalhada de pedido
- Atualizacao de status de processamento
- Download de nota fiscal (PDF)
- Visualizacao de rastreamento
- Refresh manual de dados de rastreamento

### Controller
`App\Http\Controllers\OrderController`

### Rotas
```php
GET  /dashboard/orders                          # Listar pedidos
GET  /dashboard/orders/{order}                  # Visualizar pedido
GET  /dashboard/orders/{order}/invoice/download # Download de NF
POST /dashboard/orders/{order}/refresh-tracking # Atualizar rastreamento
PATCH /dashboard/orders/{order}                 # Atualizar status
POST /dashboard/orders/fetch                    # Importacao manual
POST /dashboard/orders/import-and-sync          # Importar e sincronizar
POST /dashboard/orders/sync-bling               # Sincronizar com Bling
POST /dashboard/orders/{order}/sync-bling       # Sincronizar pedido especifico
```

### Metodos Principais

#### index()
Lista pedidos com filtros e paginacao.

**Parametros de Query**:
- `search`: Buscar por numero de pedido ou cliente
- `source`: Filtrar por sistema (pro, atacadista, beauty)
- `status`: Filtrar por status de processamento
- `date_from`: Data inicial
- `date_to`: Data final

**Exemplo de Uso**:
```
GET /dashboard/orders?source=pro&status=picking&search=12345
```

#### show()
Exibe detalhes completos de um pedido.

**Dados Retornados**:
- Informacoes basicas do pedido
- Dados do cliente
- Itens do pedido
- Nota fiscal (se existir)
- Dados de rastreamento (se existir)
- Historico de alteracoes

#### update()
Atualiza status de processamento do pedido.

**Payload**:
```json
{
    "picking": true,
    "packing": false,
    "picked_up": false
}
```

#### downloadInvoice()
Baixa PDF da nota fiscal.

**Resposta**: Stream de PDF

#### refreshTracking()
Atualiza dados de rastreamento manualmente disparando job de sync.

### Filtros Disponiveis

#### Por Sistema (source)
- `pro`: Collmepro Pro
- `atacadista`: Collmepro Atacadista
- `beauty`: Collme Beauty

#### Por Status de Processamento
- `pending`: Aguardando processamento
- `picking`: Em separacao
- `packing`: Em embalagem
- `picked_up`: Coletado

#### Por Data
- Range customizado (data_from, data_to)
- Hoje
- Ultimos 7 dias
- Ultimos 30 dias

## Sistema Kanban

### Descricao
Interface visual estilo Kanban para acompanhamento do fluxo logistico.

### Funcionalidades
- Visualizacao em colunas por etapa do processo
- Arrastar e soltar entre colunas (drag & drop)
- Atualizacao de status via click
- Atualizacao em tempo real via WebSockets
- Filtros por sistema
- Contadores por coluna

### Controller
`App\Http\Controllers\KanbanController`

### Rotas
```php
GET  /dashboard/painel                        # Pagina Kanban (deprecated - redireciona)
POST /dashboard/painel/{orderSummary}/status  # Atualizar status
```

### Colunas do Kanban

#### 1. Pedidos Emitidos
**Criterio**: Pedidos sem nota fiscal
```php
!$hasInvoice
```

**Cor**: Cinza

#### 2. Faturamento
**Criterio**: Com nota fiscal, sem separacao iniciada
```php
$hasInvoice && (!$orderStatus || !$orderStatus->picking)
```

**Cor**: Amarelo

#### 3. Checkout
**Criterio**: Em separacao, nao embalado
```php
$hasInvoice && $orderStatus->picking && !$orderStatus->packing
```

**Cor**: Azul

#### 4. Embalagem
**Criterio**: Separado e em embalagem
```php
$hasInvoice && $orderStatus->picking && $orderStatus->packing && !$hasTracking
```

**Cor**: Laranja

#### 5. Etiqueta
**Criterio**: Embalado, aguardando etiqueta
```php
$hasInvoice && $orderStatus->picking && $orderStatus->packing && !$hasTracking
```

**Cor**: Roxo

#### 6. Coleta Aguardando
**Criterio**: Etiquetado, aguardando coleta
```php
$hasInvoice && $orderStatus->picking && $orderStatus->packing && $hasTracking && !$orderStatus->picked_up
```

**Cor**: Verde

#### 7. Coleta Realizada
**Criterio**: Coletado
```php
$orderStatus->picked_up
```

**Cor**: Verde Escuro

### Atualizacao de Status

#### updateStatus()
Atualiza status de um pedido no Kanban.

**Payload**:
```json
{
    "status": "picking|packing|picked_up",
    "value": true|false
}
```

**Fluxo**:
1. Valida payload
2. Atualiza `order_status`
3. Registra usuario e timestamp
4. Cria registro em `order_history`
5. Broadcast via WebSockets
6. Retorna sucesso

### Atualizacoes em Tempo Real

O Kanban escuta eventos WebSocket para atualizacoes automaticas:

```javascript
window.Echo.channel('orders')
    .listen('OrderCreated', (e) => {
        // Adiciona novo pedido na coluna apropriada
    })
    .listen('OrderUpdated', (e) => {
        // Move pedido entre colunas ou atualiza dados
    });
```

## Gestao de Usuarios

### Descricao
Modulo para gerenciamento de usuarios do sistema (apenas Admin).

### Funcionalidades
- Listagem de usuarios
- Criar novo usuario
- Editar usuario existente
- Deletar usuario
- Atribuir roles
- Redefinir senha

### Controller
`App\Http\Controllers\UserController`

### Rotas
```php
GET    /dashboard/users            # Listar usuarios
GET    /dashboard/users/create     # Formulario de criacao
POST   /dashboard/users            # Criar usuario
GET    /dashboard/users/{user}     # Visualizar usuario
GET    /dashboard/users/{user}/edit # Formulario de edicao
PATCH  /dashboard/users/{user}     # Atualizar usuario
DELETE /dashboard/users/{user}     # Deletar usuario
```

### Validacao

#### Criar Usuario
```php
[
    'name' => 'required|string|max:255',
    'email' => 'required|string|email|max:255|unique:users',
    'username' => 'required|string|max:255|unique:users',
    'password' => 'required|string|min:8|confirmed',
    'role' => 'required|in:admin,manager,sales,developer'
]
```

#### Atualizar Usuario
```php
[
    'name' => 'required|string|max:255',
    'email' => 'required|string|email|max:255|unique:users,email,{userId}',
    'role' => 'required|in:admin,manager,sales,developer',
    'password' => 'nullable|string|min:8|confirmed'
]
```

### Restricoes
- Admin nao pode deletar a propria conta
- Deve existir pelo menos um admin no sistema
- Senhas sao sempre hasheadas

## Gestao de Permissoes

### Descricao
Modulo para configurar permissoes por role (apenas Admin).

### Funcionalidades
- Visualizar permissoes de cada role
- Ativar/desativar permissoes por role
- Visualizar categorias de permissoes
- Descricoes detalhadas de cada permissao

### Controller
`App\Http\Controllers\PermissionController`

### Rotas
```php
GET /dashboard/permissions            # Listar roles e permissoes
GET /dashboard/permissions/{role}     # Visualizar permissoes do role
GET /dashboard/permissions/{role}/edit # Formulario de edicao
PUT /dashboard/permissions/{role}     # Atualizar permissoes
```

### Estrutura de Permissoes

#### Categorias
- `orders`: Gestao de pedidos
- `users`: Gestao de usuarios
- `permissions`: Gestao de permissoes
- `system`: Configuracoes de sistema

#### Formato
```
categoria.acao
```

Exemplos:
- `orders.view`
- `orders.edit`
- `orders.import`
- `users.create`
- `users.delete`

### Permissoes Padrao por Role

#### Admin
- Todas as permissoes

#### Manager
- `orders.view`
- `orders.edit`
- `orders.import`
- `orders.status.update`

#### Sales
- `orders.view`
- `orders.status.update` (limitado)

#### Developer
- `orders.view`
- `orders.import`
- System debug

## Sincronizacao de Dados

### Descricao
Processos de sincronizacao com sistemas externos (Bling).

### Tipos de Sincronizacao

#### 1. Sincronizacao Automatica
- Executada via Laravel Scheduler
- Frequencia configuravel
- Sincronizacao incremental (ultimos 30 dias)
- Processamento em background via jobs

#### 2. Sincronizacao Manual
- Iniciada pelo usuario via interface
- Sincronizacao completa ou incremental
- Feedback em tempo real via WebSockets

### Fluxo de Sincronizacao

```
BlingSyncJob
    |
    ├─> Busca pedidos do Bling (paginado)
    |
    ├─> Para cada pedido:
    │   ├─> Verifica se existe (por bling_id)
    │   ├─> Compara hash para detectar mudancas
    │   ├─> Atualiza ou cria pedido
    │   ├─> Cria/atualiza OrderStatus
    │   └─> Dispara OrderSynced Event
    │           |
    │           ├─> SyncInvoiceJob
    │           │       └─> Busca e salva nota fiscal
    │           |
    │           ├─> ExtractTrackingJob
    │           │       └─> Extrai dados de rastreamento
    │           |
    │           └─> SyncSellerJob
    │                   └─> Busca vendedor no WooCommerce
    |
    └─> Broadcast status de sincronizacao
```

### Deteccao de Mudancas

O sistema usa hash MD5 para detectar mudancas em pedidos:

```php
$hash = md5(json_encode([
    'id' => $order['id'],
    'numero' => $order['numero'],
    'total' => $order['total'],
    'situacao' => $order['situacao'],
    'has_invoice' => !empty($order['notaFiscal']['id']),
    // ... outros campos criticos
]));
```

Se o hash for identico ao armazenado, o pedido e pulado (nao houve mudancas).

## Historico de Alteracoes

### Descricao
Sistema de auditoria que registra todas as alteracoes em pedidos.

### Funcionalidades
- Registro automatico de todas as mudancas
- Rastreamento de usuario responsavel
- Armazenamento de valores antigos e novos
- Timestamps precisos
- Metadados adicionais

### Model
`App\Models\OrderHistory`

### Service
`App\Services\OrderHistoryService`

### Tipos de Eventos Registrados

#### order_imported
Pedido importado pela primeira vez.

#### order_synced
Pedido sincronizado/atualizado.

#### invoice_synced
Nota fiscal sincronizada.

#### tracking_fetched
Dados de rastreamento obtidos.

#### status_updated
Status de processamento atualizado.

#### order_cancelled
Pedido cancelado.

### Exemplo de Registro

```php
OrderHistory::create([
    'order_summary_id' => $order->id,
    'user_id' => auth()->id(),
    'action' => 'status_updated',
    'description' => 'Order status changed to packing',
    'old_value' => json_encode(['packing' => false]),
    'new_value' => json_encode(['packing' => true]),
    'metadata' => json_encode([
        'changed_fields' => ['packing'],
        'ip_address' => request()->ip()
    ])
]);
```

### Visualizacao

Historico e exibido na tela de detalhes do pedido em ordem cronologica reversa (mais recente primeiro).

## Perfil de Usuario

### Descricao
Modulo para gerenciamento do proprio perfil (todos os usuarios).

### Funcionalidades
- Atualizar informacoes pessoais (nome, email)
- Alterar senha
- Habilitar/desabilitar 2FA
- Visualizar codigos de recuperacao 2FA

### Controller
`App\Http\Controllers\ProfileController`

### Rotas
```php
GET   /profile  # Visualizar perfil
PATCH /profile  # Atualizar perfil
```

### Validacao

```php
[
    'name' => 'required|string|max:255',
    'email' => 'required|string|email|max:255|unique:users,email,{userId}',
    'current_password' => 'required_with:password',
    'password' => 'nullable|string|min:8|confirmed'
]
```

### Seguranca
- Requer senha atual para alterar senha
- Senha hasheada com bcrypt
- 2FA opcional mas recomendado

