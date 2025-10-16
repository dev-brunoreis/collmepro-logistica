<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'display_name',
        'description',
        'category',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the role permissions for this permission.
     */
    public function rolePermissions()
    {
        return $this->hasMany(RolePermission::class);
    }

    /**
     * Get the roles that have this permission.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'role_permissions',
            'permission_id',
            'role',
            'id',
            'role'
        )->using(RolePermission::class);
    }

    /**
     * Check if a role has this permission.
     */
    public function hasRole(string $role): bool
    {
        return $this->roles()->where('role', $role)->exists();
    }

    /**
     * Get permissions by category.
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Get active permissions.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Available permission categories.
     */
    public const CATEGORIES = [
        'orders' => 'Pedidos',
        'users' => 'Usuários',
        'labels' => 'Etiquetas',
        'kanban' => 'Kanban',
        'dashboard' => 'Dashboard',
        'system' => 'Sistema',
    ];
}