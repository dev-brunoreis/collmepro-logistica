<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class AuthTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    public function test_user_can_login(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = $this->post('/logout');

        $response->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_user_can_edit_own_profile(): void
    {
        $user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $this->actingAs($user);

        $response = $this->patch('/profile', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ]);

        $response->assertRedirect('/profile');
        $response->assertSessionHas('success', 'Perfil atualizado com sucesso.');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ]);
    }

    public function test_user_cannot_edit_username(): void
    {
        $user = User::factory()->create([
            'username' => 'johndoe',
        ]);

        $this->actingAs($user);

        $response = $this->get('/profile');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => 
            $page->component('Profile/Edit')
                ->where('user.username', 'johndoe')
        );
    }

    public function test_user_can_change_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = $this->patch('/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'password' => 'newpassword',
            'password_confirmation' => 'newpassword',
        ]);

        $response->assertRedirect('/profile');
        $response->assertSessionHas('success', 'Perfil atualizado com sucesso.');

        // Verify password was changed
        $this->assertTrue(password_verify('newpassword', $user->fresh()->password));
    }

    public function test_user_cannot_change_password_without_confirmation(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = $this->patch('/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'password' => 'newpassword',
            'password_confirmation' => 'wrongpassword',
        ]);

        $response->assertSessionHasErrors(['password']);
    }
}
