# Agent Architecture for Development

## Overview

This architecture defines how AI agents should work together to develop the Collmepro logistics system progressively, following TDD, DRY, SOLID and KISS principles.

## ⚠️ URGENT TIMELINE: January 15 Deadline

**Total available time: ~15 days**
**Accelerated development required**

## Fundamental Principles

### 1. Progressive Development
- Implement functionalities in phases
- Each phase must be functional and testable
- Do not implement unsolicited functionalities
- Focus on what was specified in the PDF

### 2. Test-First Development
- Always write tests before implementation
- Tests must fail initially (Red)
- Implement minimum code to pass (Green)
- Refactor keeping tests passing (Refactor)

### 3. Code Quality
- **DRY**: Don't repeat code
- **SOLID**: Object-oriented design principles
- **KISS**: Keep solutions simple
- **Clean Code**: Readable and maintainable code

## Agent Structure

### 1. Agent Master (Coordinator)
**Responsibilities**:
- Coordinate work of other agents
- Ensure requirements are met
- Validate code quality
- Maintain project consistency

**Behavior**:
- Analyze current project state
- Define next tasks
- Delegate work to specialized agents
- Review and approve implementations

### 2. Agent Tester (Testing Specialist)
**Responsibilities**:
- Write unit and integration tests
- Create mocks for external APIs
- Validate test coverage
- Ensure tests are meaningful

**Behavior**:
- Always write tests before implementation
- Use PHPUnit for PHP tests
- Create factories for test data
- Implement API tests with mocks

**Exemplo de Trabalho**:
```php
// 1. Escrever teste que falha
public function test_can_import_woocommerce_order()
{
    $this->mock(WooCommerceService::class)
         ->shouldReceive('fetchOrders')
         ->andReturn($this->getMockOrderData());
    
    $this->post('/api/orders/import/woocommerce')
         ->assertStatus(200)
         ->assertJsonCount(1, 'data');
}

// 2. Implementar código mínimo para passar
// 3. Refatorar mantendo testes passando
```

### 3. Agent Backend (Laravel Specialist)
**Responsibilities**:
- Implement controllers, models and services
- Configure routes and middlewares
- Implement authentication and authorization
- Manage database

**Behavior**:
- Follow Laravel patterns
- Implement only what's necessary
- Use Eloquent ORM
- Apply adequate validations

**Exemplo de Trabalho**:
```php
// 1. Implementar model
class Order extends Model
{
    protected $fillable = [
        'system', 'order_number', 'customer_name', 
        'seller_name', 'order_date'
    ];
    
    public function status()
    {
        return $this->hasOne(OrderStatus::class);
    }
}

// 2. Implementar service
class OrderService
{
    public function importFromWooCommerce(array $data): Order
    {
        return Order::create($data);
    }
}
```

### 4. Agent Frontend (Vue.js Specialist)
**Responsibilities**:
- Implement Vue.js components
- Configure Inertia.js
- Implement user interface
- Manage application state

**Behavior**:
- Use Composition API
- Implement reusable components
- Follow design patterns
- Maintain responsive interface

**Exemplo de Trabalho**:
```vue
<!-- 1. Implementar componente -->
<template>
  <div class="orders-table">
    <table>
      <thead>
        <tr>
          <th>Sistema</th>
          <th>N. Pedido</th>
          <th>Cliente</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="order in orders" :key="order.id">
          <td>{{ order.system }}</td>
          <td>{{ order.order_number }}</td>
          <td>{{ order.customer_name }}</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'

const orders = ref([])

onMounted(() => {
  fetchOrders()
})

const fetchOrders = async () => {
  const response = await axios.get('/api/orders')
  orders.value = response.data
}
</script>
```

### 5. Agent Integration (API Specialist)
**Responsibilities**:
- Implement integrations with external APIs
- Create services for communication
- Implement jobs for background processing
- Manage errors and retries

**Behavior**:
- Use Laravel HTTP client
- Implement rate limiting
- Create mocks for development
- Implement error logs

**Exemplo de Trabalho**:
```php
// 1. Implementar service
class WooCommerceService
{
    public function fetchOrders(): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('woocommerce.token')
        ])->get(config('woocommerce.url') . '/orders');
        
        if ($response->failed()) {
            throw new WooCommerceException('Failed to fetch orders');
        }
        
        return $response->json();
    }
}

// 2. Implementar job
class ImportWooCommerceOrders implements ShouldQueue
{
    public function handle(WooCommerceService $service)
    {
        $orders = $service->fetchOrders();
        
        foreach ($orders as $orderData) {
            Order::create($orderData);
        }
    }
}
```

## Workflow

### 1. Requirements Analysis
1. **Agent Master** analyzes requirement
2. Defines scope and priorities
3. Identifies dependencies
4. Creates implementation plan

### 2. Test Implementation
1. **Agent Tester** writes tests
2. Tests must fail initially
3. Defines API contracts
4. Creates necessary mocks

### 3. Backend Implementation
1. **Agent Backend** implements code
2. Minimum code to pass tests
3. Applies SOLID principles
4. Implements validations

### 4. Frontend Implementation
1. **Agent Frontend** implements interface
2. Reusable components
3. Responsive interface
4. Backend integration

### 5. API Integration
1. **Agent Integration** implements services
2. Jobs for processing
3. Error handling
4. Logs and monitoring

### 6. Validation and Refactoring
1. **Agent Master** reviews implementation
2. Validates requirements met
3. Refactors code if necessary
4. Updates documentation

## Implementation Patterns

### 1. Folder Structure
```
app/
├── Http/
│   ├── Controllers/
│   ├── Middleware/
│   └── Requests/
├── Models/
├── Services/
├── Jobs/
└── Exceptions/

tests/
├── Unit/
├── Feature/
└── Mocks/

resources/
├── js/
│   ├── Components/
│   ├── Pages/
│   └── Services/
└── views/
```

### 2. Naming Conventions
- **Models**: PascalCase (Order, OrderStatus)
- **Controllers**: PascalCase + Controller (OrderController)
- **Services**: PascalCase + Service (OrderService)
- **Jobs**: PascalCase + Job (ImportOrdersJob)
- **Components**: PascalCase (OrdersTable)
- **Methods**: camelCase (fetchOrders, importOrder)

### 3. Test Patterns
```php
// Unit test
public function test_order_can_be_created()
{
    $order = Order::factory()->create();
    
    $this->assertInstanceOf(Order::class, $order);
    $this->assertNotEmpty($order->order_number);
}

// Integration test
public function test_can_import_orders_from_woocommerce()
{
    $this->mock(WooCommerceService::class)
         ->shouldReceive('fetchOrders')
         ->andReturn($this->getMockOrderData());
    
    $response = $this->post('/api/orders/import/woocommerce');
    
    $response->assertStatus(200);
    $this->assertDatabaseHas('orders', [
        'system' => 'woocommerce'
    ]);
}
```

## Development Phases

### Phase 1: Authentication and Users (2 days) ✅ COMPLETED
1. ✅ Implement authentication (Laravel Fortify)
2. ✅ Create role system (Admin, Manager, Sales)
3. ✅ Implement user CRUD
4. ✅ Authentication and authorization tests
5. ✅ **Permission System Implementation**:
   - Database-driven permissions with `permissions` and `role_permissions` tables
   - 10 distinct permissions across 5 categories
   - Middleware-based route protection with `CheckPermission` class
   - Frontend conditional rendering based on user permissions
   - All permissions tested and verified (32/32 tests passing)
6. ✅ **Security Features**:
   - Route protection for all sensitive operations
   - UI elements only visible to authorized users
   - Permission-based access control for all functionalities

### Phase 2: Order Import (3 days)
1. Implement services for WooCommerce and Shopify
2. Create jobs for background import
3. Implement data validations
4. Import tests

### Phase 3: Bling Integration (2 days)
1. Implement service for Bling API
2. Manage invoices and labels
3. Implement PDF downloads
4. Integration tests

### Phase 4: Tracking APIs (3 days)
1. Implement services for Correios, Total Express, Melhor Envio
2. Create tracking system
3. Implement automatic status updates
4. Tracking tests

### Phase 5: User Interface (4 days)
1. Implement orders screen
2. Implement Kanban screen
3. Implement labels screen
4. Implement dashboard

### Phase 6: Reports and Exports (1 day)
1. Implement reports
2. Implement exports (XLS, PDF)
3. Implement advanced filters
4. Report tests

## Quality Criteria

### 1. Test Coverage
- Minimum 60% coverage (reduced for timeline)
- Unit tests for critical classes
- Integration tests for main flows
- API tests with mocks

### 2. Code Quality
- Follow PSR-12
- Apply SOLID principles
- Readable and documented code
- No code duplication

### 3. Performance
- Optimized queries
- Adequate pagination
- Cache when necessary
- Jobs for heavy processing

### 4. Security ✅ IMPLEMENTED
- ✅ Input validation (Laravel validation rules)
- ✅ Proper authorization (Role-based access control with permissions)
- ✅ SQL injection protection (Eloquent ORM)
- ✅ Data sanitization (Laravel built-in features)
- ✅ **Permission System Security**:
  - Route protection with `CheckPermission` middleware
  - Database-driven permissions with proper relationships
  - Frontend conditional rendering based on user permissions
  - All sensitive operations protected by permission checks
  - UI elements only visible to authorized users

## Monitoring and Logs

### 1. Application Logs
- Error logs
- Integration logs
- Performance logs
- Audit logs

### 2. Metrics
- Response time
- Error rate
- Resource usage
- Test coverage

### 3. Alerts
- Integration failures
- Critical errors
- Performance degradation
- Test failures

## Conclusion

This agent architecture ensures development is:
- **Progressive**: Implementation in functional phases
- **Quality**: Tested and well-structured code
- **Efficient**: Focus on what was requested
- **Maintainable**: Clean and documented code

Each agent has specific responsibilities and works together to deliver a robust and functional system.

## 🚨 URGENT: January 15 Deadline

**This accelerated timeline requires:**
- Maximum efficiency
- Parallel development where possible
- Focus on core functionality
- Essential features only
- Comprehensive testing after core completion
