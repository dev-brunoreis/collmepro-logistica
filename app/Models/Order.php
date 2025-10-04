<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    use HasFactory;

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
        'order_status_id',
        'tracking_code',
        'logistics_provider',
        'service_type',
        'volume',
        'weight',
    ];

    protected $casts = [
        'data' => 'date',
        'data_saida' => 'date',
        'data_prevista' => 'date',
        'total' => 'decimal:2',
        'total_produtos' => 'integer',
        'contato_id' => 'integer',
        'situacao_id' => 'integer',
        'situacao_valor' => 'integer',
        'loja_id' => 'integer',
        'bling_id' => 'integer',
        'volume' => 'decimal:2',
        'weight' => 'decimal:2',
    ];

    /**
     * Get the order status associated with the order.
     */
    public function orderStatus(): BelongsTo
    {
        return $this->belongsTo(OrderStatus::class, 'id', 'order_summary_id');
    }

    /**
     * Scope to filter by source.
     */
    public function scopeBySource($query, string $source)
    {
        return $query->where('source', $source);
    }

    /**
     * Scope to filter by numero.
     */
    public function scopeByNumero($query, string $numero)
    {
        return $query->where('numero', 'like', "%{$numero}%");
    }

    /**
     * Scope to filter by situacao valor.
     */
    public function scopeBySituacaoValor($query, int $situacaoValor)
    {
        return $query->where('situacao_valor', $situacaoValor);
    }

    /**
     * Scope to filter by date range.
     */
    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('data', [$startDate, $endDate]);
    }

    /**
     * Scope to filter by contato nome.
     */
    public function scopeByContatoNome($query, string $nome)
    {
        return $query->where('contato_nome', 'like', "%{$nome}%");
    }

    /**
     * Get the formatted data.
     */
    public function getFormattedDataAttribute(): string
    {
        return $this->data->format('d/m/Y');
    }

    /**
     * Get the customer name.
     */
    public function getCustomerNameAttribute(): string
    {
        return $this->contato_nome ?? 'N/A';
    }

    /**
     * Get the customer document.
     */
    public function getCustomerDocumentAttribute(): string
    {
        return $this->contato_documento ?? 'N/A';
    }

    /**
     * Check if order is from Bling.
     */
    public function isFromBling(): bool
    {
        return $this->source === 'bling';
    }

    /**
     * Scope to filter by Bling orders.
     */
    public function scopeBling($query)
    {
        return $query->where('source', 'bling');
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
     * Get the situacao description.
     */
    public function getSituacaoDescriptionAttribute(): string
    {
        $situacoes = [
            1 => 'Em Aberto',
            2 => 'Em Andamento',
            3 => 'Finalizado',
            4 => 'Cancelado'
        ];

        return $situacoes[$this->situacao_valor] ?? 'Desconhecido';
    }

    /**
     * Check if order is finalized.
     */
    public function isFinalized(): bool
    {
        return $this->situacao_valor === 3;
    }

    /**
     * Check if order is cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->situacao_valor === 4;
    }
}
