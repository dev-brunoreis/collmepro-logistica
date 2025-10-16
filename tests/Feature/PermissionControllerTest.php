<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PermissionSeeder::class);
    }

    public function test_admin_can_view_permissions_index(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        
        $response = $this->actingAs($admin)->get('/dashboard/permissions');
        
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Permissions/Index')
            ->has('roles')
            ->has('allPermissions')
        );
    }

    public function test_manager_cannot_view_permissions_index(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        
        $response = $this->actingAs($manager)->get('/dashboard/permissions');
        
        $response->assertStatus(403);
    }

    public function test_seller_cannot_view_permissions_index(): void
    {
        $seller = User::factory()->create(['role' => 'sales']);
        
        $response = $this->actingAs($seller)->get('/dashboard/permissions');
        
        $response->assertStatus(403);
    }

    public function test_admin_can_view_role_permissions(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        
        $response = $this->actingAs($admin)->get('/dashboard/permissions/admin');
        
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Permissions/Show')
            ->has('role')
            ->has('roleData')
            ->has('permissions')
        );
    }

    public function test_admin_can_edit_role_permissions(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        
        $response = $this->actingAs($admin)->get('/dashboard/permissions/manager/edit');
        
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Permissions/Edit')
            ->has('role')
            ->has('roleData')
            ->has('rolePermissions')
            ->has('allPermissions')
        );
    }

    public function test_admin_can_update_role_permissions(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $permission = Permission::first();
        
        $response = $this->actingAs($admin)->put('/dashboard/permissions/manager', [
            'permissions' => [$permission->id]
        ]);
        
        $response->assertRedirect('/dashboard/permissions');
        $response->assertSessionHas('success');
        
        $this->assertDatabaseHas('role_permissions', [
            'role' => 'manager',
            'permission_id' => $permission->id,
        ]);
    }

    public function test_admin_cannot_remove_own_admin_permissions_if_only_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        
        // Remove all admin permissions except one
        $adminPermissions = Permission::whereHas('rolePermissions', function ($query) {
            $query->where('role', 'admin');
        })->pluck('id')->toArray();
        
        $response = $this->actingAs($admin)->put('/dashboard/permissions/admin', [
            'permissions' => [$adminPermissions[0]] // Only keep first permission
        ]);
        
        $response->assertRedirect();
        $response->assertSessionHas('error', 'Você não pode remover suas próprias permissões de administrador sendo o único admin do sistema.');
    }

    public function test_admin_can_remove_own_admin_permissions_if_multiple_admins(): void
    {
        $admin1 = User::factory()->create(['role' => 'admin']);
        $admin2 = User::factory()->create(['role' => 'admin']);
        
        $response = $this->actingAs($admin1)->put('/dashboard/permissions/admin', [
            'permissions' => []
        ]);
        
        $response->assertRedirect('/dashboard/permissions');
        $response->assertSessionHas('success');
    }

    public function test_invalid_role_returns_404(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        
        $response = $this->actingAs($admin)->get('/dashboard/permissions/invalid');
        
        $response->assertStatus(404);
    }

    public function test_user_has_permission_method(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $manager = User::factory()->create(['role' => 'manager']);
        $seller = User::factory()->create(['role' => 'sales']);
        
        // Test admin permissions
        $this->assertTrue($admin->hasPermission('orders.view'));
        $this->assertTrue($admin->hasPermission('orders.status.update'));
        $this->assertTrue($admin->hasPermission('users.view'));
        $this->assertTrue($admin->hasPermission('permissions.view'));
        
        // Test manager permissions
        $this->assertTrue($manager->hasPermission('orders.view'));
        $this->assertTrue($manager->hasPermission('orders.status.update'));
        $this->assertFalse($manager->hasPermission('users.view'));
        $this->assertFalse($manager->hasPermission('permissions.view'));
        
        // Test seller permissions
        $this->assertTrue($seller->hasPermission('orders.view'));
        $this->assertFalse($seller->hasPermission('orders.status.update'));
        $this->assertFalse($seller->hasPermission('users.view'));
        $this->assertFalse($seller->hasPermission('permissions.view'));
    }

    public function test_user_permissions_method(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        
        $permissions = $admin->permissions();
        
        $this->assertNotEmpty($permissions);
        $this->assertTrue($permissions->contains('name', 'orders.view'));
        $this->assertTrue($permissions->contains('name', 'users.view'));
    }
}