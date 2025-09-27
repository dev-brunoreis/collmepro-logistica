<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Label extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'label_number',
        'status',
        'issued_at',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
    ];

    /**
     * Get the order that owns the label.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Scope to filter by label number.
     */
    public function scopeByLabelNumber($query, string $labelNumber)
    {
        return $query->where('label_number', 'like', "%{$labelNumber}%");
    }

    /**
     * Scope to filter by status.
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to filter by date range.
     */
    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('issued_at', [$startDate, $endDate]);
    }

    /**
     * Scope to filter by today.
     */
    public function scopeToday($query)
    {
        return $query->whereDate('issued_at', today());
    }

    /**
     * Get the formatted issued date.
     */
    public function getFormattedIssuedDateAttribute(): string
    {
        return $this->issued_at->format('d/m/Y H:i');
    }

    /**
     * Check if label is issued.
     */
    public function isIssued(): bool
    {
        return $this->status === 'issued';
    }

    /**
     * Check if label is posted.
     */
    public function isPosted(): bool
    {
        return $this->status === 'posted';
    }

    /**
     * Check if label is expired.
     */
    public function isExpired(): bool
    {
        return $this->status === 'expired';
    }

    /**
     * Get the status badge color.
     */
    public function getStatusBadgeColor(): string
    {
        return match ($this->status) {
            'issued' => 'blue',
            'posted' => 'green',
            'expired' => 'red',
            default => 'gray',
        };
    }

    /**
     * Get the status label in Portuguese.
     */
    public function getStatusLabel(): string
    {
        return match ($this->status) {
            'issued' => 'Emitido',
            'posted' => 'Postado',
            'expired' => 'Expirado',
            default => 'Desconhecido',
        };
    }
}
