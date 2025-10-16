# API e Rotas

## Visao Geral

O sistema utiliza rotas web tradicionais do Laravel com Inertia.js, nao possui API REST separada. Todas as rotas retornam views Inertia ou respostas JSON quando apropriado.

## Estrutura de Rotas

### Arquivos de Rotas

- **routes/web.php**: Rotas principais da aplicacao
- **routes/auth.php**: Rotas de autenticacao (Fortify)
- **routes/settings.php**: Rotas de configuracoes
- **routes/channels.php**: Canais de broadcasting

## Middleware Aplicado

### Global
- `web`: Middleware web padrao do Laravel
- `EncryptCookies`
- `AddQueuedCookiesToResponse`
- `StartSession`
- `ShareErrorsFromSession`
- `VerifyCsrfToken`
- `SubstituteBindings`

### Aplicado Especificamente
- `auth`: Requer usuario autenticado
- `verified`: Requer email verificado
- `permission:{name}`: Verifica permissao especifica

## Rotas Publicas

### Pagina Inicial

```
GET /
```

Redireciona para dashboard se autenticado, senao para login.

## Rotas de Autenticacao

### Login

```
GET  /login
POST /login
```

**Parametros POST**:
```json
{
    "email": "user@example.com",
    "password": "password"
}
```

### Logout

```
POST /logout
```

### Redefinicao de Senha

```
GET  /forgot-password
POST /forgot-password
GET  /reset-password/{token}
POST /reset-password
```

### Verificacao de Email

```
GET  /verify-email
POST /email/verification-notification
GET  /verify-email/{id}/{hash}
```

## Rotas do Dashboard

Prefixo: `/dashboard`  
Middleware: `auth`, `verified`

### Dashboard Principal

```
GET /dashboard
GET /dashboard/data
GET /dashboard/api/sync-status
```

**GET /dashboard**  
Retorna: Pagina Inertia `Dashboard`

**GET /dashboard/data**  
Parametros Query:
- `days_back`: Numero de dias (default: 7)
- `load_all`: Carregar todos (boolean)

Retorna: JSON
```json
{
    "metrics": {...},
    "ordersByStatus": {...},
    "recentActivity": [...],
    "kanbanData": {...}
}
```

**GET /dashboard/api/sync-status**  
Retorna: JSON
```json
{
    "is_running": false,
    "last_sync_at": "2025-01-16T14:30:00Z",
    "account_type": "all",
    "updated_at": "2025-01-16T14:35:00Z"
}
```

## Rotas de Pedidos

Prefixo: `/dashboard/orders`

### Listagem

```
GET /dashboard/orders
```

Middleware: `permission:orders.view`

Parametros Query:
- `search`: Buscar por numero ou cliente
- `source`: Filtrar por sistema (pro, atacadista, beauty)
- `status`: Filtrar por status
- `date_from`: Data inicial (Y-m-d)
- `date_to`: Data final (Y-m-d)
- `page`: Numero da pagina

Retorna: Pagina Inertia `Orders/Index`

### Visualizar Pedido

```
GET /dashboard/orders/{order}
```

Middleware: `permission:orders.view`

Retorna: Pagina Inertia `Orders/Show`

### Atualizar Status

```
PATCH /dashboard/orders/{order}
```

Middleware: `permission:orders.status.update`

Payload:
```json
{
    "picking": true,
    "packing": false,
    "picked_up": false
}
```

Retorna: Redirect back com mensagem de sucesso

### Download de Nota Fiscal

```
GET /dashboard/orders/{order}/invoice/download
```

Middleware: `permission:orders.view`

Retorna: PDF stream

### Refresh Tracking

```
POST /dashboard/orders/{order}/refresh-tracking
```

Middleware: `permission:orders.view`

Retorna: JSON
```json
{
    "message": "Tracking refresh queued successfully"
}
```

### Importacao e Sincronizacao Manual

```
POST /dashboard/orders/fetch
POST /dashboard/orders/import-and-sync
POST /dashboard/orders/sync-bling
POST /dashboard/orders/{order}/sync-bling
```

Middleware: `permission:orders.import`

## Rotas de Kanban

### Atualizar Status no Kanban

```
POST /dashboard/painel/{orderSummary}/status
```

Middleware: `permission:orders.status.update`

Payload:
```json
{
    "status": "picking",
    "value": true
}
```

Status validos: `picking`, `packing`, `picked_up`

Retorna: JSON
```json
{
    "success": true,
    "order": {...}
}
```

## Rotas de Usuarios

Prefixo: `/dashboard/users`  
Middleware base: `auth`, `verified`

### Listar Usuarios

```
GET /dashboard/users
```

Middleware: `permission:users.view`

Retorna: Pagina Inertia `Users/Index`

### Criar Usuario

```
GET  /dashboard/users/create
POST /dashboard/users
```

Middleware: `permission:users.create`

**Payload POST**:
```json
{
    "name": "Nome Completo",
    "email": "email@example.com",
    "username": "username",
    "password": "password123",
    "password_confirmation": "password123",
    "role": "manager"
}
```

Retorna: Redirect para `/dashboard/users`

### Visualizar Usuario

```
GET /dashboard/users/{user}
```

Middleware: `permission:users.view`

Retorna: Pagina Inertia `Users/Show`

### Editar Usuario

```
GET   /dashboard/users/{user}/edit
PATCH /dashboard/users/{user}
```

Middleware: `permission:users.edit`

**Payload PATCH**:
```json
{
    "name": "Nome Atualizado",
    "email": "newemail@example.com",
    "role": "admin",
    "password": "newpassword123",
    "password_confirmation": "newpassword123"
}
```

Retorna: Redirect para `/dashboard/users`

### Deletar Usuario

```
DELETE /dashboard/users/{user}
```

Middleware: `permission:users.delete`

Retorna: Redirect para `/dashboard/users`

## Rotas de Permissoes

Prefixo: `/dashboard/permissions`  
Middleware: `permission:permissions.view`

### Listar Permissoes

```
GET /dashboard/permissions
```

Retorna: Pagina Inertia `Permissions/Index`

### Visualizar Permissoes de Role

```
GET /dashboard/permissions/{role}
```

Retorna: Pagina Inertia `Permissions/Show`

### Editar Permissoes de Role

```
GET /dashboard/permissions/{role}/edit
PUT /dashboard/permissions/{role}
```

Middleware: `permission:permissions.edit`

**Payload PUT**:
```json
{
    "permissions": [1, 2, 3, 5, 8]
}
```

Retorna: Redirect para `/dashboard/permissions`

## Rotas de Perfil

Middleware: `auth`

### Editar Perfil

```
GET   /profile
PATCH /profile
```

**Payload PATCH**:
```json
{
    "name": "Nome Atualizado",
    "email": "newemail@example.com",
    "current_password": "currentpassword",
    "password": "newpassword",
    "password_confirmation": "newpassword"
}
```

Retorna: Redirect back com mensagem

## Canais de Broadcasting

### Canal orders

```
Channel: orders
Eventos:
  - OrderCreated
  - OrderUpdated
  - OrderDeleted
```

**Autorizacao**: Todos os usuarios autenticados

**Eventos**:

**OrderCreated**
```json
{
    "order": {
        "id": 123,
        "numero": "12345",
        ...
    }
}
```

**OrderUpdated**
```json
{
    "order": {
        "id": 123,
        "numero": "12345",
        ...
    },
    "changed_fields": ["picking", "packing"],
    "change_types": ["status_changed"]
}
```

### Canal sync-status

```
Channel: sync-status
Eventos:
  - BlingSyncStatusUpdated
```

**Autorizacao**: Todos os usuarios autenticados

**Evento BlingSyncStatusUpdated**:
```json
{
    "is_running": true,
    "last_sync_at": "2025-01-16T14:30:00Z",
    "next_sync_at": null,
    "account_type": "pro"
}
```

## Parametros Comuns

### Paginacao

Parametros Query:
- `page`: Numero da pagina (default: 1)
- `per_page`: Itens por pagina (default: 15, max: 100)

Resposta Inertia inclui:
```json
{
    "data": [...],
    "current_page": 1,
    "last_page": 10,
    "per_page": 15,
    "total": 150
}
```

### Ordenacao

Parametros Query:
- `sort`: Campo para ordenar
- `direction`: `asc` ou `desc`

Exemplo: `/dashboard/orders?sort=data&direction=desc`

### Filtros

Parametros Query especificos por recurso:
- `search`: Busca textual
- `source`: Filtro por sistema
- `status`: Filtro por status
- `date_from`, `date_to`: Range de datas

## Respostas

### Sucesso (2xx)

**200 OK**: Requisicao bem sucedida
```json
{
    "success": true,
    "data": {...}
}
```

**201 Created**: Recurso criado
```json
{
    "success": true,
    "data": {...},
    "message": "Resource created successfully"
}
```

**204 No Content**: Sucesso sem conteudo de retorno

### Redirecionamentos (3xx)

**302 Found**: Redirect temporario (usado pelo Inertia)

### Erros Cliente (4xx)

**400 Bad Request**: Dados invalidos
```json
{
    "message": "The given data was invalid.",
    "errors": {
        "email": ["The email field is required."]
    }
}
```

**401 Unauthorized**: Nao autenticado
```json
{
    "message": "Unauthenticated."
}
```

**403 Forbidden**: Sem permissao
```json
{
    "message": "Unauthorized action."
}
```

**404 Not Found**: Recurso nao encontrado
```json
{
    "message": "Resource not found."
}
```

**419 Page Expired**: Token CSRF expirado
```json
{
    "message": "Page expired."
}
```

**422 Unprocessable Entity**: Validacao falhou
```json
{
    "message": "The given data was invalid.",
    "errors": {
        "name": ["The name field is required."],
        "email": ["The email has already been taken."]
    }
}
```

**429 Too Many Requests**: Rate limit excedido
```json
{
    "message": "Too many requests."
}
```

### Erros Servidor (5xx)

**500 Internal Server Error**: Erro interno
```json
{
    "message": "Server Error"
}
```

**503 Service Unavailable**: Servico indisponivel
```json
{
    "message": "Service Unavailable"
}
```

## Exemplos de Uso

### JavaScript/Axios

```javascript
// Buscar dados do dashboard
const response = await axios.get('/dashboard/data', {
    params: { days_back: 30 }
});

// Atualizar status de pedido
await axios.patch(`/dashboard/orders/${orderId}`, {
    picking: true,
    packing: false
});

// Criar usuario
await axios.post('/dashboard/users', {
    name: 'John Doe',
    email: 'john@example.com',
    username: 'johndoe',
    password: 'secret123',
    password_confirmation: 'secret123',
    role: 'manager'
});
```

### Inertia.js (Vue)

```vue
<script setup>
import { useForm } from '@inertiajs/vue3';

// Atualizar status
const form = useForm({
    picking: true,
    packing: false
});

const submit = () => {
    form.patch(`/dashboard/orders/${orderId}`);
};

// Criar usuario
const userForm = useForm({
    name: '',
    email: '',
    username: '',
    password: '',
    password_confirmation: '',
    role: 'manager'
});

const createUser = () => {
    userForm.post('/dashboard/users');
};
</script>
```

## Rate Limiting

O sistema aplica rate limiting em certas rotas:

- **Login**: 5 tentativas por minuto
- **API Routes**: 60 requisicoes por minuto
- **Bling API**: 0.5 req/s por worker (limitacao customizada)

## CORS

Nao aplicavel pois o sistema nao possui API externa. Todas as requisicoes sao same-origin.

## Versionamento

O sistema nao possui versionamento de API pois usa rotas web tradicionais.

