<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'username',
        'password',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Available user roles
     */
    public const ROLES = [
        'admin' => 'Administrador',
        'manager' => 'Gerente',
        'sales' => 'Vendas',
    ];

    /**
     * Check if user is admin
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Check if user is manager
     */
    public function isManager(): bool
    {
        return $this->role === 'manager';
    }

    /**
     * Check if user is sales
     */
    public function isSales(): bool
    {
        return $this->role === 'sales';
    }

    /**
     * Check if user has admin or manager role
     */
    public function canManageUsers(): bool
    {
        return $this->isAdmin();
    }

    /**
     * Check if user can access orders screen
     */
    public function canAccessOrders(): bool
    {
        return $this->isAdmin() || $this->isManager();
    }

    /**
     * Check if user can access kanban screen
     */
    public function canAccessKanban(): bool
    {
        return true; // All roles can access kanban
    }

    /**
     * Check if user can access labels screen
     */
    public function canAccessLabels(): bool
    {
        return true; // All roles can access labels
    }

    /**
     * Check if user can access dashboard
     */
    public function canAccessDashboard(): bool
    {
        return true; // All roles can access dashboard
    }

    /**
     * Get user role display name
     */
    public function getRoleDisplayNameAttribute(): string
    {
        return self::ROLES[$this->role] ?? $this->role;
    }
}
