<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderHistory extends Model
{
    use HasFactory;

    protected $table = 'order_histories';

    protected $fillable = [
        'order_summary_id',
        'event_type',
        'event_description',
        'user_id',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    // Event types constants
    const EVENT_ORDER_IMPORTED = 'order_imported';
    const EVENT_STATUS_PICKING = 'status_picking';
    const EVENT_STATUS_PACKING = 'status_packing';
    const EVENT_STATUS_PICKED_UP = 'status_picked_up';
    const EVENT_INVOICE_SYNCED = 'invoice_synced';
    const EVENT_TRACKING_FETCHED = 'tracking_fetched';
    const EVENT_NOTE_ADDED = 'note_added';
    const EVENT_TRACKING_UPDATED = 'tracking_updated';
    const EVENT_MANUAL_SYNC = 'manual_sync';
    const EVENT_ORDER_UPDATED = 'order_updated';

    /**
     * Get the order summary that owns this history entry.
     */
    public function orderSummary(): BelongsTo
    {
        return $this->belongsTo(OrderSummary::class);
    }

    /**
     * Get the user who performed the action.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to filter by event type.
     */
    public function scopeByEventType($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    /**
     * Scope to order by most recent first.
     */
    public function scopeRecent($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    /**
     * Scope to filter by date range.
     */
    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Get formatted event description.
     */
    public function getFormattedDescriptionAttribute(): string
    {
        return match ($this->event_type) {
            self::EVENT_ORDER_IMPORTED => 'Pedido importado do sistema externo',
            self::EVENT_STATUS_PICKING => $this->metadata['status'] ? 'Status "Separado" marcado como concluído' : 'Status "Separado" desmarcado',
            self::EVENT_STATUS_PACKING => $this->metadata['status'] ? 'Status "Embalado" marcado como concluído' : 'Status "Embalado" desmarcado',
            self::EVENT_STATUS_PICKED_UP => $this->metadata['status'] ? 'Status "Coletado" marcado como concluído' : 'Status "Coletado" desmarcado',
            self::EVENT_INVOICE_SYNCED => 'NFE sincronizada com Bling',
            self::EVENT_TRACKING_FETCHED => 'Dados de rastreamento obtidos',
            self::EVENT_NOTE_ADDED => 'Nota adicionada ao pedido',
            self::EVENT_TRACKING_UPDATED => 'Rastreamento atualizado',
            self::EVENT_MANUAL_SYNC => 'Sincronização manual executada',
            self::EVENT_ORDER_UPDATED => 'Pedido atualizado',
            default => $this->event_description,
        };
    }

    /**
     * Get event icon.
     */
    public function getEventIconAttribute(): string
    {
        return match ($this->event_type) {
            self::EVENT_ORDER_IMPORTED => 'download',
            self::EVENT_STATUS_PICKING => 'package',
            self::EVENT_STATUS_PACKING => 'box',
            self::EVENT_STATUS_PICKED_UP => 'truck',
            self::EVENT_INVOICE_SYNCED => 'file-text',
            self::EVENT_TRACKING_FETCHED => 'search',
            self::EVENT_NOTE_ADDED => 'message-square',
            self::EVENT_TRACKING_UPDATED => 'refresh-cw',
            self::EVENT_MANUAL_SYNC => 'refresh-cw',
            self::EVENT_ORDER_UPDATED => 'edit',
            default => 'activity',
        };
    }

    /**
     * Get event color.
     */
    public function getEventColorAttribute(): string
    {
        return match ($this->event_type) {
            self::EVENT_ORDER_IMPORTED => 'blue',
            self::EVENT_STATUS_PICKING, self::EVENT_STATUS_PACKING, self::EVENT_STATUS_PICKED_UP => 'green',
            self::EVENT_INVOICE_SYNCED, self::EVENT_TRACKING_FETCHED => 'purple',
            self::EVENT_NOTE_ADDED => 'yellow',
            self::EVENT_TRACKING_UPDATED, self::EVENT_MANUAL_SYNC, self::EVENT_ORDER_UPDATED => 'orange',
            default => 'gray',
        };
    }
}
