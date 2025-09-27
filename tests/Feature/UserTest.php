<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    public function test_admin_can_create_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin);

        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'username' => 'johndoe',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'manager',
        ];

        $response = $this->post('/users', $userData);

        $response->assertRedirect('/users');
        $response->assertSessionHas('success', 'Usuário criado com sucesso.');

        $this->assertDatabaseHas('users', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'username' => 'johndoe',
            'role' => 'manager',
        ]);
    }

    public function test_manager_cannot_create_user(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);

        $this->actingAs($manager);

        $response = $this->get('/users');

        $response->assertStatus(403);
    }

    public function test_sales_cannot_create_user(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);

        $this->actingAs($sales);

        $response = $this->get('/users');

        $response->assertStatus(403);
    }

    public function test_admin_can_edit_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'role' => 'sales',
        ]);

        $this->actingAs($admin);

        $response = $this->put("/users/{$user->id}", [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'username' => $user->username,
            'role' => 'manager',
        ]);

        $response->assertRedirect('/users');
        $response->assertSessionHas('success', 'Usuário atualizado com sucesso.');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'role' => 'manager',
        ]);
    }

    public function test_admin_can_delete_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();

        $this->actingAs($admin);

        $response = $this->delete("/users/{$user->id}");

        $response->assertRedirect('/users');
        $response->assertSessionHas('success', 'Usuário excluído com sucesso.');

        $this->assertDatabaseMissing('users', [
            'id' => $user->id,
        ]);
    }

    public function test_admin_cannot_delete_themselves(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin);

        $response = $this->delete("/users/{$admin->id}");

        $response->assertRedirect('/users');
        $response->assertSessionHas('error', 'Você não pode excluir sua própria conta.');

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
        ]);
    }

    public function test_user_validation_requires_unique_email(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $existingUser = User::factory()->create(['email' => 'existing@example.com']);

        $this->actingAs($admin);

        $response = $this->post('/users', [
            'name' => 'John Doe',
            'email' => 'existing@example.com',
            'username' => 'johndoe',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'sales',
        ]);

        $response->assertSessionHasErrors(['email']);
    }

    public function test_user_validation_requires_unique_username(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $existingUser = User::factory()->create(['username' => 'existinguser']);

        $this->actingAs($admin);

        $response = $this->post('/users', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'username' => 'existinguser',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'sales',
        ]);

        $response->assertSessionHasErrors(['username']);
    }

    public function test_user_validation_requires_valid_role(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin);

        $response = $this->post('/users', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'username' => 'johndoe',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'invalid_role',
        ]);

        $response->assertSessionHasErrors(['role']);
    }

    public function test_admin_cannot_change_own_role(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin);

        $response = $this->put("/users/{$admin->id}", [
            'name' => $admin->name,
            'email' => $admin->email,
            'username' => $admin->username,
            'role' => 'manager', // Trying to change own role
        ]);

        $response->assertRedirect('/users');
        $response->assertSessionHas('success');
        
        // Verify role was not changed
        $admin->refresh();
        $this->assertEquals('admin', $admin->role);
    }
}
