<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderStatusHistory extends Model
{
    use HasFactory;

    protected $table = 'order_status_history';

    protected $fillable = [
        'order_id',
        'status_type',
        'status_value',
        'user_id',
        'changed_at',
    ];

    protected $casts = [
        'status_value' => 'boolean',
        'changed_at' => 'datetime',
    ];

    /**
     * Get the order that owns the status history.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the user who made the change.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to filter by status type.
     */
    public function scopeByStatusType($query, string $statusType)
    {
        return $query->where('status_type', $statusType);
    }

    /**
     * Scope to filter by status value.
     */
    public function scopeByStatusValue($query, bool $statusValue)
    {
        return $query->where('status_value', $statusValue);
    }

    /**
     * Scope to order by most recent first.
     */
    public function scopeRecent($query)
    {
        return $query->orderBy('changed_at', 'desc');
    }
}