<?php

namespace App\Models;

use Database\Factories\LabelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class Label extends Model
{
    use HasFactory;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): LabelFactory
    {
        return LabelFactory::new();
    }

    protected $fillable = [
        'order_summary_id',
        'bling_label_id',
        'tracking_code',
        'format',
        'issue_date',
        'link',
        'observation',
        'raw_data',
    ];

    protected $casts = [
        'issue_date' => 'datetime',
        'raw_data' => 'array',
    ];

    /**
     * Get the order summary that owns this label
     */
    public function orderSummary(): BelongsTo
    {
        return $this->belongsTo(OrderSummary::class);
    }

    /**
     * Get the tracking record associated with this label (via tracking_code)
     */
    public function tracking(): ?Tracking
    {
        if (!$this->tracking_code) {
            return null;
        }
        
        return Tracking::where('codigo_rastreamento', $this->tracking_code)->first();
    }

    /**
     * Check if label is posted (based on tracking status)
     * Posted = tracking exists and situacao >= 2 (dispatched or in transit)
     */
    public function getIsPostedAttribute(): bool
    {
        $tracking = $this->tracking();
        
        if (!$tracking) {
            return false;
        }
        
        // Situacao >= 2 means dispatched, in transit, or delivered
        return $tracking->situacao >= 2;
    }

    /**
     * Get status text in Portuguese
     */
    public function getStatusTextAttribute(): string
    {
        return $this->is_posted ? 'Postado' : 'Não Postado';
    }

    /**
     * Scope to filter only posted labels
     */
    public function scopePosted(Builder $query): Builder
    {
        return $query->whereHas('orderSummary.invoice.tracking', function ($q) {
            $q->where('situacao', '>=', 2);
        });
    }

    /**
     * Scope to filter only not posted labels
     */
    public function scopeNotPosted(Builder $query): Builder
    {
        return $query->whereDoesntHave('orderSummary.invoice.tracking', function ($q) {
            $q->where('situacao', '>=', 2);
        })->orWhereHas('orderSummary.invoice.tracking', function ($q) {
            $q->where('situacao', '<', 2);
        });
    }

    /**
     * Scope to filter labels generated today
     */
    public function scopeGeneratedToday(Builder $query): Builder
    {
        return $query->whereDate('issue_date', Carbon::today());
    }

    /**
     * Scope to filter labels by date range
     */
    public function scopeByDateRange(Builder $query, ?string $startDate, ?string $endDate): Builder
    {
        if ($startDate) {
            $query->where('issue_date', '>=', Carbon::parse($startDate)->startOfDay());
        }
        
        if ($endDate) {
            $query->where('issue_date', '<=', Carbon::parse($endDate)->endOfDay());
        }
        
        return $query;
    }

    /**
     * Scope for search functionality
     */
    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (!$search) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->where('tracking_code', 'like', "%{$search}%")
              ->orWhere('bling_label_id', 'like', "%{$search}%")
              ->orWhere('observation', 'like', "%{$search}%")
              ->orWhereHas('orderSummary', function ($subQ) use ($search) {
                  $subQ->where('numero', 'like', "%{$search}%")
                       ->orWhere('contato_nome', 'like', "%{$search}%");
              });
        });
    }
}

