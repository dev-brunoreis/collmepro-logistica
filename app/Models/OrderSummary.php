<?php

namespace App\Models;

use Database\Factories\OrderSummaryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'raw_invoice_data',
        'raw_tracking_data',
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
        'raw_invoice_data' => 'array',
        'raw_tracking_data' => 'array',
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
     * Check if order has tracking.
     */
    public function hasTracking(): bool
    {
        return !empty($this->tracking_code);
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
}
