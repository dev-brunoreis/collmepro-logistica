# Autenticacao e Autorizacao

## Visao Geral

O sistema utiliza Laravel Fortify para autenticacao e um sistema customizado de permissoes baseado em roles para autorizacao. O sistema suporta autenticacao de dois fatores (2FA) e gerenciamento granular de permissoes.

## Sistema de Autenticacao

### Laravel Fortify

O sistema utiliza Laravel Fortify para gerenciar:
- Login/Logout
- Registro de usuarios (desabilitado por padrao)
- Redefinicao de senha
- Verificacao de email
- Autenticacao de dois fatores (2FA)

### Configuracao

Arquivo: `config/fortify.php`

```php
'features' => [
    Features::updateProfileInformation(),
    Features::updatePasswords(),
    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]),
],
```

### Rotas de Autenticacao

Arquivo: `routes/auth.php`

```php
// Login
GET  /login
POST /login

// Logout
POST /logout

// Password Reset
GET  /forgot-password
POST /forgot-password
GET  /reset-password/{token}
POST /reset-password

// Email Verification
GET  /verify-email
POST /email/verification-notification
GET  /verify-email/{id}/{hash}
```

## Perfis de Usuario (Roles)

O sistema possui 4 niveis de acesso:

### 1. Admin (Administrador)
**Acesso:** Total ao sistema

**Permissoes:**
- Gerenciar usuarios (criar, editar, deletar)
- Gerenciar permissoes
- Visualizar e gerenciar todos os pedidos
- Importar pedidos manualmente
- Atualizar status de pedidos
- Acessar dashboard e metricas
- Acessar Kanban
- Configuracoes do sistema

**Uso Tipico:** Administradores do sistema

### 2. Manager (Gerente)
**Acesso:** Gestao operacional

**Permissoes:**
- Visualizar todos os pedidos
- Atualizar status de pedidos
- Importar pedidos manualmente
- Acessar dashboard e metricas
- Acessar Kanban
- Gerenciar seu proprio perfil

**Uso Tipico:** Gerentes de logistica e operacoes

### 3. Sales (Vendedor)
**Acesso:** Visualizacao e operacao basica

**Permissoes:**
- Visualizar pedidos
- Acessar Kanban
- Acessar dashboard (visualizacao)
- Gerenciar seu proprio perfil

**Uso Tipico:** Equipe de vendas e atendimento

### 4. Developer (Desenvolvedor)
**Acesso:** Desenvolvimento e debugging

**Permissoes:**
- Acesso total para desenvolvimento
- Acesso a logs e debugging
- Gerenciar importacoes e sincronizacoes

**Uso Tipico:** Equipe de desenvolvimento

## Sistema de Permissoes

### Estrutura de Permissoes

Permissoes seguem o padrao: `recurso.acao`

Exemplos:
```
orders.view         # Visualizar pedidos
orders.edit         # Editar pedidos
orders.import       # Importar pedidos
orders.status.update # Atualizar status de pedidos
users.view          # Visualizar usuarios
users.create        # Criar usuarios
users.edit          # Editar usuarios
users.delete        # Deletar usuarios
permissions.view    # Visualizar permissoes
permissions.edit    # Editar permissoes
```

### Model Permission

Arquivo: `app/Models/Permission.php`

```php
class Permission extends Model
{
    protected $fillable = [
        'name',
        'display_name',
        'description',
        'category',
        'is_active'
    ];
}
```

### Model RolePermission

Arquivo: `app/Models/RolePermission.php`

```php
class RolePermission extends Model
{
    protected $fillable = [
        'role',
        'permission_id'
    ];

    public function permission()
    {
        return $this->belongsTo(Permission::class);
    }
}
```

### Verificacao de Permissoes

#### No Model User

```php
// Verificar se usuario tem permissao
public function hasPermission(string $permissionName): bool
{
    return RolePermission::where('role', $this->role)
        ->whereHas('permission', function ($query) use ($permissionName) {
            $query->where('name', $permissionName)
                  ->where('is_active', true);
        })
        ->exists();
}

// Obter todas as permissoes do usuario
public function permissions()
{
    return RolePermission::where('role', $this->role)
        ->with('permission')
        ->get()
        ->pluck('permission');
}
```

#### No Controller

```php
// Verificar permissao manualmente
if (!auth()->user()->hasPermission('orders.edit')) {
    abort(403, 'Unauthorized action.');
}
```

## Middleware de Autorizacao

### PermissionMiddleware

Arquivo: `app/Http/Middleware/PermissionMiddleware.php`

```php
public function handle(Request $request, Closure $next, string $permission)
{
    if (!$request->user()) {
        return redirect()->route('login');
    }

    if (!$request->user()->hasPermission($permission)) {
        abort(403, 'Unauthorized action.');
    }

    return $next($request);
}
```

### Registro do Middleware

Arquivo: `bootstrap/app.php`

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'permission' => \App\Http\Middleware\PermissionMiddleware::class,
    ]);
})
```

### Uso em Rotas

```php
// Proteger rota com permissao
Route::middleware(['auth', 'permission:orders.view'])->group(function () {
    Route::get('orders', [OrderController::class, 'index'])
        ->name('orders.index');
});

// Multiplas permissoes
Route::middleware(['auth', 'permission:orders.edit'])->group(function () {
    Route::patch('orders/{order}', [OrderController::class, 'update'])
        ->name('orders.update');
});
```

## Gestao de Sessoes

### Configuracao

Arquivo: `config/session.php`

```php
'driver' => env('SESSION_DRIVER', 'file'),
'lifetime' => env('SESSION_LIFETIME', 120),
'expire_on_close' => false,
```

### Sessoes no Frontend

O Inertia.js compartilha automaticamente dados de sessao com o frontend:

```php
// Arquivo: app/Http/Middleware/HandleInertiaRequests.php
public function share(Request $request): array
{
    return [
        ...parent::share($request),
        'auth' => [
            'user' => $request->user() ? [
                'id' => $request->user()->id,
                'name' => $request->user()->name,
                'email' => $request->user()->email,
                'role' => $request->user()->role,
                'permissions' => $request->user()->permissions()->pluck('name'),
            ] : null,
        ],
    ];
}
```

### Acesso no Frontend

```vue
<script setup>
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const user = computed(() => page.props.auth.user);
const permissions = computed(() => page.props.auth.user?.permissions || []);

const hasPermission = (permission) => {
    return permissions.value.includes(permission);
};
</script>

<template>
    <div v-if="hasPermission('orders.edit')">
        <!-- Conteudo visivel apenas para usuarios com permissao orders.edit -->
    </div>
</template>
```

## Autenticacao de Dois Fatores (2FA)

### Habilitar 2FA

1. Usuario acessa perfil
2. Clica em "Habilitar autenticacao de dois fatores"
3. Sistema gera QR Code
4. Usuario escaneia com app autenticador (Google Authenticator, Authy, etc)
5. Usuario confirma com codigo gerado

### Fluxo de Login com 2FA

1. Usuario fornece email e senha
2. Sistema valida credenciais
3. Se 2FA habilitado, redireciona para tela de codigo
4. Usuario fornece codigo de 6 digitos
5. Sistema valida e autentica

### Codigos de Recuperacao

Ao habilitar 2FA, o sistema gera 8 codigos de recuperacao que podem ser usados caso o usuario perca acesso ao dispositivo.

## Redefinicao de Senha

### Fluxo

1. Usuario acessa "Esqueci minha senha"
2. Fornece email
3. Sistema envia email com link de redefinicao
4. Usuario clica no link e define nova senha

### Configuracao de Email

Arquivo: `.env`

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="noreply@collmepro.com"
MAIL_FROM_NAME="${APP_NAME}"
```

## Verificacao de Email

### Habilitar Verificacao

No model User:

```php
class User extends Authenticatable implements MustVerifyEmail
{
    // ...
}
```

### Proteger Rotas

```php
Route::middleware(['auth', 'verified'])->group(function () {
    // Rotas que requerem email verificado
});
```

## Gerenciamento de Usuarios

### UserController

Arquivo: `app/Http/Controllers/UserController.php`

#### Listar Usuarios

```php
public function index()
{
    $users = User::orderBy('name')->paginate(20);
    
    return Inertia::render('Users/Index', [
        'users' => $users
    ]);
}
```

#### Criar Usuario

```php
public function store(Request $request)
{
    $validated = $request->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|string|email|max:255|unique:users',
        'username' => 'required|string|max:255|unique:users',
        'password' => 'required|string|min:8|confirmed',
        'role' => 'required|in:admin,manager,sales,developer'
    ]);

    $validated['password'] = Hash::make($validated['password']);

    User::create($validated);

    return redirect()->route('users.index');
}
```

#### Atualizar Usuario

```php
public function update(Request $request, User $user)
{
    $validated = $request->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
        'role' => 'required|in:admin,manager,sales,developer',
        'password' => 'nullable|string|min:8|confirmed'
    ]);

    if (isset($validated['password'])) {
        $validated['password'] = Hash::make($validated['password']);
    } else {
        unset($validated['password']);
    }

    $user->update($validated);

    return redirect()->route('users.index');
}
```

#### Deletar Usuario

```php
public function destroy(User $user)
{
    // Nao permitir deletar proprio usuario
    if ($user->id === auth()->id()) {
        return back()->withErrors(['error' => 'You cannot delete your own account.']);
    }

    $user->delete();

    return redirect()->route('users.index');
}
```

## Seguranca

### Protecao CSRF

Todas as requisicoes POST, PUT, PATCH, DELETE possuem protecao CSRF automatica do Laravel.

### Rate Limiting

O sistema aplica rate limiting em rotas de autenticacao:

```php
// Login: 5 tentativas por minuto
RateLimiter::for('login', function (Request $request) {
    return Limit::perMinute(5)->by($request->email.$request->ip());
});
```

### Hashing de Senhas

Senhas sao hasheadas usando bcrypt via `Hash::make()`.

### XSS Protection

Vue.js escapa automaticamente conteudo interpolado.

### SQL Injection Protection

Eloquent utiliza prepared statements automaticamente.

## Auditoria

Todas as acoes de usuarios sao registradas na tabela `order_histories` quando relacionadas a pedidos:

```php
OrderHistory::create([
    'order_summary_id' => $order->id,
    'user_id' => auth()->id(),
    'action' => 'order_updated',
    'description' => 'Order status updated',
    'old_value' => json_encode($oldData),
    'new_value' => json_encode($newData)
]);
```

## Boas Praticas

### Sempre Verificar Permissoes

```php
// CORRETO
Route::middleware(['permission:orders.edit'])->group(function () {
    Route::patch('orders/{order}', [OrderController::class, 'update']);
});

// INCORRETO - sem verificacao de permissao
Route::patch('orders/{order}', [OrderController::class, 'update']);
```

### Nao Confiar em Dados do Frontend

```php
// CORRETO - valida role no backend
$validated = $request->validate([
    'role' => 'required|in:admin,manager,sales,developer'
]);

// INCORRETO - aceita qualquer valor
$user->role = $request->role;
```

### Usar Middleware Apropriado

```php
// Para rotas que requerem autenticacao
Route::middleware(['auth'])->group(function () {
    // ...
});

// Para rotas que requerem autenticacao E verificacao de email
Route::middleware(['auth', 'verified'])->group(function () {
    // ...
});

// Para rotas que requerem autenticacao E permissao especifica
Route::middleware(['auth', 'permission:resource.action'])->group(function () {
    // ...
});
```

## Troubleshooting

### Usuario Nao Consegue Logar

1. Verificar se email esta correto
2. Verificar se senha esta correta
3. Verificar se conta esta ativa
4. Verificar se 2FA esta habilitado (e se codigo esta correto)

### Permissoes Nao Funcionam

1. Verificar se permissao existe na tabela `permissions`
2. Verificar se role_permission existe
3. Verificar se permissao esta ativa (`is_active = true`)
4. Limpar cache: `php artisan cache:clear`

### 2FA Nao Funciona

1. Verificar se horario do servidor esta correto (TOTP depende de horario)
2. Verificar se app autenticador esta sincronizado
3. Usar codigo de recuperacao se necessario

