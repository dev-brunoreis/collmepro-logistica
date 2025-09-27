<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderStatus extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'picking',
        'picking_marked_by',
        'picking_marked_at',
        'packing',
        'packing_marked_by',
        'packing_marked_at',
        'picked_up',
        'picked_up_marked_by',
        'picked_up_marked_at',
    ];

    protected $casts = [
        'picking' => 'boolean',
        'packing' => 'boolean',
        'picked_up' => 'boolean',
        'picking_marked_at' => 'datetime',
        'packing_marked_at' => 'datetime',
        'picked_up_marked_at' => 'datetime',
    ];

    /**
     * Get the order that owns the status.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the user who marked picking.
     */
    public function pickingMarkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'picking_marked_by');
    }

    /**
     * Get the user who marked packing.
     */
    public function packingMarkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'packing_marked_by');
    }

    /**
     * Get the user who marked picked up.
     */
    public function pickedUpMarkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'picked_up_marked_by');
    }

    /**
     * Mark picking as completed.
     */
    public function markPicking(User $user): void
    {
        $this->update([
            'picking' => true,
            'picking_marked_by' => $user->id,
            'picking_marked_at' => now(),
        ]);
    }

    /**
     * Mark packing as completed.
     */
    public function markPacking(User $user): void
    {
        $this->update([
            'packing' => true,
            'packing_marked_by' => $user->id,
            'packing_marked_at' => now(),
        ]);
    }

    /**
     * Mark picked up as completed.
     */
    public function markPickedUp(User $user): void
    {
        $this->update([
            'picked_up' => true,
            'picked_up_marked_by' => $user->id,
            'picked_up_marked_at' => now(),
        ]);
    }

    /**
     * Unmark picking.
     */
    public function unmarkPicking(): void
    {
        $this->update([
            'picking' => false,
            'picking_marked_by' => null,
            'picking_marked_at' => null,
        ]);
    }

    /**
     * Unmark packing.
     */
    public function unmarkPacking(): void
    {
        $this->update([
            'packing' => false,
            'packing_marked_by' => null,
            'packing_marked_at' => null,
        ]);
    }

    /**
     * Unmark picked up.
     */
    public function unmarkPickedUp(): void
    {
        $this->update([
            'picked_up' => false,
            'picked_up_marked_by' => null,
            'picked_up_marked_at' => null,
        ]);
    }

    /**
     * Check if all statuses are completed.
     */
    public function isCompleted(): bool
    {
        return $this->picking && $this->packing && $this->picked_up;
    }

    /**
     * Get the completion percentage.
     */
    public function getCompletionPercentage(): int
    {
        $completed = 0;
        if ($this->picking) $completed++;
        if ($this->packing) $completed++;
        if ($this->picked_up) $completed++;

        return round(($completed / 3) * 100);
    }

    /**
     * Get the next status to complete.
     */
    public function getNextStatus(): ?string
    {
        if (!$this->picking) return 'picking';
        if (!$this->packing) return 'packing';
        if (!$this->picked_up) return 'picked_up';
        
        return null; // All completed
    }

    /**
     * Scope to filter by picking status.
     */
    public function scopePicking($query, bool $picking = true)
    {
        return $query->where('picking', $picking);
    }

    /**
     * Scope to filter by packing status.
     */
    public function scopePacking($query, bool $packing = true)
    {
        return $query->where('packing', $packing);
    }

    /**
     * Scope to filter by picked up status.
     */
    public function scopePickedUp($query, bool $pickedUp = true)
    {
        return $query->where('picked_up', $pickedUp);
    }

    /**
     * Scope to filter by completion status.
     */
    public function scopeCompleted($query)
    {
        return $query->where('picking', true)
                    ->where('packing', true)
                    ->where('picked_up', true);
    }

    /**
     * Scope to filter by pending status.
     */
    public function scopePending($query)
    {
        return $query->where(function ($q) {
            $q->where('picking', false)
              ->orWhere('packing', false)
              ->orWhere('picked_up', false);
        });
    }
}
