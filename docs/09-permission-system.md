# Permission System Documentation

## Overview

The Collmepro Logistics System implements a comprehensive Role-Based Access Control (RBAC) system with three distinct user roles and 10 specific permissions. This system ensures that users can only access the functionalities they are authorized to use.

## System Architecture

### Database Structure

#### Tables
1. **`permissions`** - Stores all available permissions
   - `id` (Primary Key)
   - `name` (Unique permission identifier)
   - `display_name` (Human-readable name)
   - `description` (Permission description)
   - `category` (Permission category)
   - `is_active` (Boolean flag)
   - `created_at`, `updated_at`

2. **`role_permissions`** - Links roles to permissions
   - `id` (Primary Key)
   - `role` (Role name: admin, manager, sales)
   - `permission_id` (Foreign key to permissions)
   - `created_at`, `updated_at`

3. **`users`** - User accounts with roles
   - `id` (Primary Key)
   - `name`, `email`, `username`
   - `password` (Hashed)
   - `role` (admin, manager, sales)
   - `created_at`, `updated_at`

### Models

#### User Model
```php
class User extends Authenticatable
{
    // Role constants
    public const ROLES = [
        'admin' => 'Administrador',
        'manager' => 'Gerente',
        'sales' => 'Vendas',
    ];

    // Permission methods
    public function hasPermission(string $permissionName): bool
    public function permissions()
    public function canAccessOrders(): bool
    public function canAccessKanban(): bool
    public function canAccessLabels(): bool
    public function canAccessDashboard(): bool
}
```

#### Permission Model
```php
class Permission extends Model
{
    public const CATEGORIES = [
        'orders' => 'Pedidos',
        'users' => 'Usuários',
        'labels' => 'Etiquetas',
        'kanban' => 'Kanban',
        'dashboard' => 'Dashboard',
        'system' => 'Sistema',
    ];
}
```

#### RolePermission Model
```php
class RolePermission extends Model
{
    public const ROLES = [
        'admin' => 'Administrador',
        'manager' => 'Gerente',
        'sales' => 'Vendas',
    ];
}
```

## Permission Definitions

### Permission Categories

#### 1. Orders Permissions
- **`orders.view`** - View orders list
- **`orders.status.update`** - Update order status (picking, packing, picked_up)

#### 2. Users Permissions
- **`users.view`** - View users list
- **`users.create`** - Create new users
- **`users.edit`** - Edit user information
- **`users.delete`** - Delete users

#### 3. Permissions Management
- **`permissions.view`** - View and manage role permissions

#### 4. Dashboard Permissions
- **`dashboard.view`** - Access main dashboard

#### 5. Kanban Permissions
- **`kanban.view`** - View kanban board

#### 6. Labels Permissions
- **`labels.view`** - View shipping labels

## Role Definitions

### Admin Role
**Full system access with all permissions:**
- ✅ `orders.view` - View orders list
- ✅ `orders.status.update` - Update order status
- ✅ `users.view` - View users list
- ✅ `users.create` - Create new users
- ✅ `users.edit` - Edit user information
- ✅ `users.delete` - Delete users
- ✅ `permissions.view` - View and manage role permissions
- ✅ `dashboard.view` - Access main dashboard
- ✅ `kanban.view` - View kanban board
- ✅ `labels.view` - View shipping labels

### Manager Role
**Operational access with limited administrative permissions:**
- ✅ `orders.view` - View orders list
- ✅ `orders.status.update` - Update order status
- ❌ `users.*` - No user management access
- ❌ `permissions.*` - No permission management access
- ✅ `dashboard.view` - Access main dashboard
- ✅ `kanban.view` - View kanban board
- ✅ `labels.view` - View shipping labels

### Sales Role
**Read-only access for sales team:**
- ✅ `orders.view` - View orders list (read-only)
- ❌ `orders.status.update` - Cannot update order status
- ❌ `users.*` - No user management access
- ❌ `permissions.*` - No permission management access
- ✅ `dashboard.view` - Access main dashboard
- ✅ `kanban.view` - View kanban board
- ✅ `labels.view` - View shipping labels

## Implementation Details

### Backend Security

#### Middleware Protection
```php
// CheckPermission Middleware
class CheckPermission
{
    public function handle(Request $request, Closure $next, string ...$permissions)
    {
        $user = $request->user();
        
        foreach ($permissions as $permission) {
            if ($user->hasPermission($permission)) {
                return $next($request);
            }
        }
        
        abort(403, 'Acesso negado. Permissões insuficientes.');
    }
}
```

#### Route Protection
```php
// Routes protected by permissions
Route::middleware(['auth', 'permission:orders.view'])->group(function () {
    Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
});

Route::middleware(['auth', 'permission:orders.status.update'])->group(function () {
    Route::patch('orders/{order}', [OrderController::class, 'update'])->name('orders.update');
});
```

#### Controller Permission Checks
```php
// OrderController
public function update(Request $request, Order $order)
{
    $user = Auth::user();
    
    if (!$user->hasPermission('orders.status.update')) {
        return back()->with('error', 'Você não tem permissão para alterar o status do pedido.');
    }
    
    // Update logic...
}
```

### Frontend Security

#### Conditional Rendering
```vue
<!-- Orders/Index.vue -->
<template>
  <button
    v-if="canUpdateStatus"
    @click="updateOrderStatus(order.id, 'picking', order.order_status?.picking || false)"
    class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-sm hover:bg-sidebar-border/70"
  >
    <!-- Button content -->
  </button>
  <div v-else class="inline-flex items-center gap-1 px-2 py-1 text-sm text-muted-foreground">
    <!-- Read-only display -->
  </div>
</template>

<script setup>
const page = usePage();
const user = page.props.auth.user;
const canUpdateStatus = user?.permissions?.some(p => p.name === 'orders.status.update') || false;
</script>
```

#### Menu Visibility
```vue
<!-- AppSidebar.vue -->
<template>
  <NavItem
    title="Pedidos"
    href="/orders"
    icon="Package"
    :visible="user?.permissions?.some(p => p.name === 'orders.view') || false"
  />
  <NavItem
    title="Usuários"
    href="/users"
    icon="Users"
    :visible="user?.permissions?.some(p => p.name === 'users.view') || false"
  />
</template>
```

### Data Sharing

#### Inertia Middleware
```php
// HandleInertiaRequests
public function share(Request $request): array
{
    return [
        'auth' => [
            'user' => $request->user() ? [
                ...$request->user()->toArray(),
                'permissions' => $request->user()->permissions(),
            ] : null,
        ],
    ];
}
```

## Testing

### Test Coverage
- **OrderControllerTest**: 11/11 tests passing
- **PermissionControllerTest**: 11/11 tests passing
- **UserTest**: 10/10 tests passing
- **Total**: 32/32 tests passing (185 assertions)

### Test Examples

#### Permission Testing
```php
public function test_sales_cannot_update_order_status(): void
{
    $sales = User::factory()->create(['role' => 'sales']);
    
    $response = $this->actingAs($sales)->patch('/orders/1', [
        'action' => 'picking',
        'status' => true,
    ]);
    
    $response->assertStatus(403);
}

public function test_manager_can_update_order_status(): void
{
    $manager = User::factory()->create(['role' => 'manager']);
    
    $response = $this->actingAs($manager)->patch('/orders/1', [
        'action' => 'picking',
        'status' => true,
    ]);
    
    $response->assertStatus(200);
}
```

## Database Seeding

### PermissionSeeder
```php
class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            [
                'name' => 'orders.view',
                'display_name' => 'Visualizar Pedidos',
                'description' => 'Permite visualizar a lista de pedidos',
                'category' => 'orders',
                'roles' => ['admin', 'manager', 'sales'],
            ],
            [
                'name' => 'orders.status.update',
                'display_name' => 'Atualizar Status dos Pedidos',
                'description' => 'Permite alterar o status dos pedidos',
                'category' => 'orders',
                'roles' => ['admin', 'manager'],
            ],
            // ... more permissions
        ];

        foreach ($permissions as $permissionData) {
            $permission = Permission::create($permissionData);
            
            foreach ($permissionData['roles'] as $role) {
                RolePermission::create([
                    'role' => $role,
                    'permission_id' => $permission->id,
                ]);
            }
        }
    }
}
```

## Security Best Practices

### 1. Defense in Depth
- **Route Level**: Middleware protection
- **Controller Level**: Permission checks
- **Frontend Level**: Conditional rendering
- **Database Level**: Proper relationships

### 2. Principle of Least Privilege
- Users only get permissions they need
- Sales role has minimal permissions
- Manager role has operational permissions only
- Admin role has full access

### 3. Consistent Implementation
- All routes protected by middleware
- All UI elements check permissions
- All database operations validate permissions
- All tests verify permission behavior

## Maintenance

### Adding New Permissions
1. Add permission to `PermissionSeeder`
2. Update role assignments
3. Add middleware protection to routes
4. Update frontend conditional rendering
5. Add tests for new permission
6. Run seeder to update database

### Modifying Role Permissions
1. Update `PermissionSeeder` role assignments
2. Run seeder to update database
3. Test all affected functionalities
4. Update documentation

### Monitoring
- Check permission assignments regularly
- Monitor failed permission attempts
- Review user access patterns
- Audit permission changes

## Troubleshooting

### Common Issues

#### Permission Not Working
1. Check if permission exists in database
2. Verify role has permission assigned
3. Check middleware is applied to route
4. Verify frontend permission check

#### UI Elements Not Showing
1. Check user permissions in browser console
2. Verify permission name matches exactly
3. Check conditional rendering logic
4. Verify Inertia data sharing

#### 403 Errors
1. Check user role and permissions
2. Verify middleware configuration
3. Check route protection
4. Review permission logic

### Debug Commands
```bash
# Check user permissions
php artisan tinker
$user = User::find(1);
$user->permissions();

# Check role permissions
RolePermission::where('role', 'sales')->with('permission')->get();

# Run permission seeder
php artisan db:seed --class=PermissionSeeder
```

## Conclusion

The permission system provides a robust, secure, and maintainable way to control access to system functionalities. It follows security best practices and ensures that users can only access what they are authorized to use, both at the backend and frontend levels.
