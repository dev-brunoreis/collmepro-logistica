<?php

namespace App\Models;

use App\Services\CarrierService;
use Database\Factories\OrderSummaryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class OrderSummary extends Model
{
    use HasFactory;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): OrderSummaryFactory
    {
        return OrderSummaryFactory::new();
    }

    protected $table = 'order_summary';

    protected $fillable = [
        'bling_id',
        'numero',
        'numero_loja',
        'data',
        'data_saida',
        'data_prevista',
        'total_produtos',
        'total',
        'contato_id',
        'contato_nome',
        'contato_tipo_pessoa',
        'contato_documento',
        'situacao_id',
        'situacao_valor',
        'loja_id',
        'source',
        'data_processed',
        'external_updated_at',
        'tracking_code',
        'logistics_provider',
        'service_type',
        'volume',
        'weight',
        'raw_order_data',
        'bling_data_hash',
    ];

    protected $casts = [
        'data' => 'datetime',
        'data_saida' => 'datetime',
        'data_prevista' => 'datetime',
        'external_updated_at' => 'datetime',
        'data_processed' => 'boolean',
        'total' => 'decimal:2',
        'total_produtos' => 'integer',
        'situacao_valor' => 'integer',
        'volume' => 'decimal:2',
        'weight' => 'decimal:2',
        'raw_order_data' => 'array',
    ];

    /**
     * Get the order data associated with this summary
     */
    public function orderData(): HasOne
    {
        return $this->hasOne(OrderData::class);
    }

    /**
     * Get the invoice associated with this summary
     */
    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    /**
     * Get the order status associated with this summary
     */
    public function orderStatus(): HasOne
    {
        return $this->hasOne(OrderStatus::class, 'order_summary_id', 'id');
    }

    /**
     * Get the sellers associated with this order
     */
    public function seller(): BelongsToMany
    {
        return $this->belongsToMany(Seller::class, 'order_summary_seller')
            ->withTimestamps();
    }

    /**
     * Scope to get unprocessed orders
     */
    public function scopeUnprocessed($query)
    {
        return $query->where('data_processed', false);
    }

    /**
     * Scope to get orders by source
     */
    public function scopeBySource($query, string $source)
    {
        return $query->where('source', $source);
    }

    /**
     * Scope to filter by bling_id.
     */
    public function scopeByBlingId($query, int $blingId)
    {
        return $query->where('bling_id', $blingId);
    }

    /**
     * Scope to filter by tracking code.
     */
    public function scopeByTrackingCode($query, string $trackingCode)
    {
        return $query->where('tracking_code', $trackingCode);
    }

    /**
     * Scope to filter out cancelled orders.
     * Cancelled orders have situacao_id = 12 in Bling.
     */
    public function scopeNotCancelled($query)
    {
        return $query->where('situacao_id', '!=', 12);
    }

    /**
     * Check if order has tracking.
     */
    public function hasTracking(): bool
    {
        return !empty($this->tracking_code);
    }

    /**
     * Check if order is cancelled.
     * Cancelled orders have situacao_id = 12 in Bling.
     */
    public function isCancelled(): bool
    {
        return $this->situacao_id === 12;
    }

    /**
     * Check if order is from Bling.
     */
    public function isFromBling(): bool
    {
        return $this->source === 'bling';
    }

    /**
     * Get the order history associated with this summary
     */
    public function orderHistory(): HasMany
    {
        return $this->hasMany(OrderHistory::class)->recent();
    }

    /**
     * Get all order history entries
     */
    public function getAllOrderHistory(): HasMany
    {
        return $this->hasMany(OrderHistory::class);
    }

    /**
     * Get the formatted carrier display name.
     */
    public function getCarrierDisplayAttribute(): string
    {
        return CarrierService::getCarrierDisplay($this->service_type);
    }
}
