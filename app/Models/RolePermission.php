<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RolePermission extends Model
{
    use HasFactory;

    protected $fillable = [
        'role',
        'permission_id',
    ];

    /**
     * Get the permission that owns the role permission.
     */
    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class);
    }

    /**
     * Available roles.
     */
    public const ROLES = [
        'admin' => 'Administrador',
        'manager' => 'Gerente',
        'sales' => 'Vendedor',
        'developer' => 'Desenvolvedor',
    ];

    /**
     * Get role display name.
     */
    public function getRoleDisplayNameAttribute(): string
    {
        return self::ROLES[$this->role] ?? $this->role;
    }
}