<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class Tracking extends Model
{
    use HasFactory;

    protected $table = 'tracking';

    protected $fillable = [
        'invoice_id',
        'volume_id',
        'pedido_venda_id',
        'nota_fiscal_id',
        'servico_id',
        'embalagem_id',
        'codigo_rastreamento',
        'descricao',
        'situacao',
        'origem',
        'destino',
        'ultima_alteracao',
        'url_rastreamento',
        'peso',
        'altura',
        'largura',
        'comprimento',
        'diametro',
        'data_saida',
        'prazo_entrega_previsto',
        'frete_previsto',
        'valor_declarado',
        'aviso_recebimento',
        'mao_propria',
        'last_sync_at',
        'next_sync_at',
        'sync_attempts',
        'last_error',
        'raw_data',
    ];

    protected $casts = [
        'ultima_alteracao' => 'datetime',
        'data_saida' => 'date',
        'peso' => 'decimal:2',
        'altura' => 'decimal:2',
        'largura' => 'decimal:2',
        'comprimento' => 'decimal:2',
        'diametro' => 'decimal:2',
        'frete_previsto' => 'decimal:2',
        'valor_declarado' => 'decimal:2',
        'aviso_recebimento' => 'boolean',
        'mao_propria' => 'boolean',
        'last_sync_at' => 'datetime',
        'next_sync_at' => 'datetime',
        'raw_data' => 'array',
    ];

    /**
     * Get the invoice that owns this tracking
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Get the order summary through the invoice
     */
    public function orderSummary()
    {
        return $this->hasOneThrough(
            OrderSummary::class,
            Invoice::class,
            'id',
            'id',
            'invoice_id',
            'order_summary_id'
        );
    }

    /**
     * Check if tracking needs sync
     */
    public function needsSync(): bool
    {
        if ($this->next_sync_at === null) {
            return true;
        }

        return $this->next_sync_at->isPast();
    }

    /**
     * Mark as synced
     */
    public function markAsSynced(): void
    {
        $this->update([
            'last_sync_at' => now(),
            'next_sync_at' => now()->addMinutes(5), // Próxima sincronização em 5 minutos
            'sync_attempts' => 0,
            'last_error' => null,
        ]);
    }

    /**
     * Mark sync as failed
     */
    public function markSyncFailed(string $error): void
    {
        $attempts = $this->sync_attempts + 1;
        $nextSync = now()->addMinutes(min(60, pow(2, $attempts))); // Exponential backoff

        $this->update([
            'sync_attempts' => $attempts,
            'last_error' => $error,
            'next_sync_at' => $nextSync,
        ]);
    }

    /**
     * Get tracking status description
     */
    public function getStatusDescriptionAttribute(): string
    {
        return match ($this->situacao) {
            1 => 'Postado',
            2 => 'Em trânsito',
            3 => 'Saiu para entrega',
            4 => 'Entregue',
            5 => 'Devolvido',
            6 => 'Extraviado',
            7 => 'Aguardando retirada',
            8 => 'Criado',
            default => 'Status desconhecido',
        };
    }

    /**
     * Check if tracking is delivered
     */
    public function isDelivered(): bool
    {
        return $this->situacao === 4;
    }

    /**
     * Check if tracking has issues
     */
    public function hasIssues(): bool
    {
        return in_array($this->situacao, [5, 6]); // Devolvido ou Extraviado
    }

    /**
     * Scope for tracking that needs sync
     */
    public function scopeNeedsSync($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('next_sync_at')
              ->orWhere('next_sync_at', '<=', now());
        });
    }

    /**
     * Scope for tracking with errors
     */
    public function scopeWithErrors($query)
    {
        return $query->whereNotNull('last_error');
    }

    /**
     * Scope for delivered tracking
     */
    public function scopeDelivered($query)
    {
        return $query->where('situacao', 4);
    }

    /**
     * Scope for tracking with issues
     */
    public function scopeWithIssues($query)
    {
        return $query->whereIn('situacao', [5, 6]);
    }
}
