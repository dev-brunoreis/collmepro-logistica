# Testes

## Visao Geral

O sistema utiliza PHPUnit para testes automatizados, seguindo a metodologia TDD (Test-Driven Development). Os testes garantem a qualidade e confiabilidade do codigo.

## Estrutura de Testes

```
tests/
├── Feature/           # Testes de integracao
│   ├── Auth/         # Testes de autenticacao
│   ├── Orders/       # Testes de pedidos
│   └── Users/        # Testes de usuarios
├── Unit/             # Testes unitarios
│   ├── Models/       # Testes de models
│   ├── Services/     # Testes de services
│   └── Jobs/         # Testes de jobs
├── CreatesApplication.php
└── TestCase.php
```

## Configuracao

### Arquivo phpunit.xml

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit>
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory>tests/Feature</directory>
        </testsuite>
    </testsuites>
    <php>
        <env name="APP_ENV" value="testing"/>
        <env name="DB_CONNECTION" value="sqlite"/>
        <env name="DB_DATABASE" value=":memory:"/>
    </php>
</phpunit>
```

### Banco de Dados para Testes

Testes utilizam SQLite em memoria para performance:

```php
// .env.testing
DB_CONNECTION=sqlite
DB_DATABASE=:memory:
```

## Executar Testes

### Todos os Testes

```bash
php artisan test
```

### Testes com Cobertura

```bash
php artisan test --coverage
```

### Teste Especifico

```bash
php artisan test --filter=test_user_can_login
```

### Suite Especifica

```bash
php artisan test tests/Feature
php artisan test tests/Unit
```

### Via Composer

```bash
composer run test
```

## Testes Unitarios

### Models

**Exemplo**: `tests/Unit/Models/OrderTest.php`

```php
<?php

namespace Tests\Unit\Models;

use App\Models\Order;
use Tests\TestCase;

class OrderTest extends TestCase
{
    public function test_order_has_tracking()
    {
        $order = Order::factory()->create([
            'tracking_code' => 'AA123456789BR'
        ]);
        
        $this->assertTrue($order->hasTracking());
    }
    
    public function test_order_without_tracking()
    {
        $order = Order::factory()->create([
            'tracking_code' => null
        ]);
        
        $this->assertFalse($order->hasTracking());
    }
    
    public function test_order_is_cancelled()
    {
        $order = Order::factory()->create([
            'situacao_id' => 12
        ]);
        
        $this->assertTrue($order->isCancelled());
    }
}
```

### Services

**Exemplo**: `tests/Unit/Services/CarrierServiceTest.php`

```php
<?php

namespace Tests\Unit\Services;

use App\Services\CarrierService;
use Tests\TestCase;

class CarrierServiceTest extends TestCase
{
    public function test_maps_pac_correctly()
    {
        $result = CarrierService::getCarrierDisplay('PAC');
        $this->assertEquals('Correios - PAC', $result);
    }
    
    public function test_maps_sedex_correctly()
    {
        $result = CarrierService::getCarrierDisplay('SEDEX');
        $this->assertEquals('Correios - SEDEX', $result);
    }
    
    public function test_returns_na_for_empty()
    {
        $result = CarrierService::getCarrierDisplay(null);
        $this->assertEquals('N/A', $result);
    }
}
```

### Jobs

**Exemplo**: `tests/Unit/Jobs/BlingSyncJobTest.php`

```php
<?php

namespace Tests\Unit\Jobs;

use App\Jobs\BlingSyncJob;
use App\Models\OrderSummary;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BlingSyncJobTest extends TestCase
{
    public function test_sync_job_creates_order()
    {
        Http::fake([
            'api.bling.com.br/*' => Http::response([
                'data' => [
                    [
                        'id' => 123,
                        'numero' => '12345',
                        'total' => 100.50,
                    ]
                ]
            ])
        ]);
        
        $job = new BlingSyncJob();
        $job->handle();
        
        $this->assertDatabaseHas('order_summary', [
            'bling_id' => 123,
            'numero' => '12345'
        ]);
    }
}
```

## Testes de Feature

### Autenticacao

**Exemplo**: `tests/Feature/Auth/LoginTest.php`

```php
<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Tests\TestCase;

class LoginTest extends TestCase
{
    public function test_user_can_login()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password')
        ]);
        
        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'password'
        ]);
        
        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }
    
    public function test_user_cannot_login_with_wrong_password()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password')
        ]);
        
        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword'
        ]);
        
        $response->assertSessionHasErrors();
        $this->assertGuest();
    }
}
```

### Pedidos

**Exemplo**: `tests/Feature/Orders/OrderControllerTest.php`

```php
<?php

namespace Tests\Feature\Orders;

use App\Models\User;
use App\Models\OrderSummary;
use Tests\TestCase;

class OrderControllerTest extends TestCase
{
    public function test_user_can_view_orders_list()
    {
        $user = User::factory()->create(['role' => 'admin']);
        OrderSummary::factory()->count(5)->create();
        
        $response = $this->actingAs($user)
            ->get('/dashboard/orders');
        
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => 
            $page->component('Orders/Index')
        );
    }
    
    public function test_user_can_update_order_status()
    {
        $user = User::factory()->create(['role' => 'manager']);
        $order = OrderSummary::factory()->create();
        
        $response = $this->actingAs($user)
            ->patch("/dashboard/orders/{$order->id}", [
                'picking' => true
            ]);
        
        $response->assertRedirect();
        
        $this->assertDatabaseHas('order_statuses', [
            'order_summary_id' => $order->id,
            'picking' => true
        ]);
    }
    
    public function test_sales_cannot_update_order_status()
    {
        $user = User::factory()->create(['role' => 'sales']);
        $order = OrderSummary::factory()->create();
        
        $response = $this->actingAs($user)
            ->patch("/dashboard/orders/{$order->id}", [
                'picking' => true
            ]);
        
        $response->assertStatus(403);
    }
}
```

### Usuarios

**Exemplo**: `tests/Feature/Users/UserControllerTest.php`

```php
<?php

namespace Tests\Feature\Users;

use App\Models\User;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    public function test_admin_can_create_user()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        
        $response = $this->actingAs($admin)
            ->post('/dashboard/users', [
                'name' => 'New User',
                'email' => 'newuser@example.com',
                'username' => 'newuser',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role' => 'manager'
            ]);
        
        $response->assertRedirect('/dashboard/users');
        
        $this->assertDatabaseHas('users', [
            'email' => 'newuser@example.com',
            'role' => 'manager'
        ]);
    }
    
    public function test_manager_cannot_create_user()
    {
        $manager = User::factory()->create(['role' => 'manager']);
        
        $response = $this->actingAs($manager)
            ->post('/dashboard/users', [
                'name' => 'New User',
                'email' => 'newuser@example.com',
                'role' => 'admin'
            ]);
        
        $response->assertStatus(403);
    }
}
```

## Mocks para APIs Externas

### Bling API

```php
use Illuminate\Support\Facades\Http;

Http::fake([
    'api.bling.com.br/Api/v3/pedidos/vendas*' => Http::response([
        'data' => [
            [
                'id' => 123,
                'numero' => '12345',
                'total' => 100.50,
                'contato' => [
                    'nome' => 'Cliente Teste'
                ]
            ]
        ]
    ]),
    
    'api.bling.com.br/Api/v3/nfe/*' => Http::response([
        'data' => [
            'id' => 456,
            'numero' => '000123',
            'chaveAcesso' => '12345678901234567890'
        ]
    ]),
]);
```

### WooCommerce API

```php
Http::fake([
    '*/wp-json/wc/v3/orders/*' => Http::response([
        'id' => 10001,
        'seller_id' => 789,
        'seller_name' => 'Vendedor Teste',
        'seller_email' => 'vendedor@example.com'
    ])
]);
```

## Factories

### UserFactory

```php
<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'username' => fake()->unique()->userName(),
            'password' => Hash::make('password'),
            'role' => fake()->randomElement(['admin', 'manager', 'sales']),
        ];
    }
}
```

### OrderSummaryFactory

```php
<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class OrderSummaryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'bling_id' => fake()->unique()->numberBetween(100000, 999999),
            'numero' => fake()->unique()->numerify('#####'),
            'numero_loja' => fake()->numerify('10###'),
            'data' => fake()->dateTimeBetween('-30 days', 'now'),
            'total' => fake()->randomFloat(2, 50, 1000),
            'contato_nome' => fake()->name(),
            'contato_documento' => fake()->numerify('###########'),
            'situacao_id' => fake()->randomElement([6, 9, 15]),
            'situacao_valor' => 2,
            'loja_id' => fake()->randomElement([204303730, 204651922]),
            'source' => fake()->randomElement(['pro', 'atacadista']),
        ];
    }
}
```

## Cobertura de Codigo

### Meta Minima

- **60%** de cobertura geral (reduzido devido ao prazo)
- **80%** de cobertura para models
- **70%** de cobertura para services
- **60%** de cobertura para controllers

### Gerar Relatorio

```bash
php artisan test --coverage --min=60
```

### Visualizar Cobertura Detalhada

```bash
php artisan test --coverage-html coverage-report
```

Abre `coverage-report/index.html` no navegador.

## Boas Praticas

### Arrange-Act-Assert (AAA)

```php
public function test_example()
{
    // Arrange: Preparar dados
    $user = User::factory()->create();
    
    // Act: Executar acao
    $response = $this->actingAs($user)->get('/dashboard');
    
    // Assert: Verificar resultado
    $response->assertStatus(200);
}
```

### Usar Factories

```php
// BOM
$order = OrderSummary::factory()->create(['total' => 100]);

// RUIM
$order = OrderSummary::create([
    'bling_id' => 123,
    'numero' => '12345',
    // ... muitos campos
]);
```

### Mockar APIs Externas

```php
// BOM
Http::fake(['api.bling.com.br/*' => Http::response([...])]);

// RUIM
// Fazer requisicoes reais em testes
```

### Um Assert por Teste

```php
// BOM
public function test_user_can_login() { ... }
public function test_user_is_redirected_after_login() { ... }

// RUIM
public function test_login_flow() {
    // Multiplos asserts nao relacionados
}
```

### Testar Edge Cases

```php
public function test_handles_null_tracking_code()
public function test_handles_empty_string_tracking_code()
public function test_handles_invalid_tracking_code_format()
```

## Continuous Integration (CI)

### GitHub Actions

```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    
    steps:
      - uses: actions/checkout@v2
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: 8.2
          
      - name: Install Dependencies
        run: composer install
        
      - name: Run Tests
        run: php artisan test --coverage --min=60
```

## Troubleshooting

### Erro de Banco de Dados

```php
// Limpar banco antes de cada teste
public function setUp(): void
{
    parent::setUp();
    $this->artisan('migrate:fresh');
}
```

### Erro de Permissoes

```php
// Criar usuario com permissoes
$user = User::factory()->create(['role' => 'admin']);
// Ou mockar permissoes
$this->mock(User::class)
    ->shouldReceive('hasPermission')
    ->andReturn(true);
```

### Timeout em Testes

```php
// Aumentar timeout no phpunit.xml
<phpunit processTimeout="300">
```

