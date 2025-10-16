<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OrderData extends Model
{
    use HasFactory;

    protected $table = 'order_data';

    protected $fillable = [
        'order_summary_id',
        'numero_pedido_compra',
        'outras_despesas',
        'observacoes',
        'observacoes_internas',
        'desconto_valor',
        'desconto_unidade',
        'categoria_id',
        'nota_fiscal_id',
        'total_icms',
        'total_ipi',
        'vendedor_id',
        'intermediador_cnpj',
        'intermediador_nome_usuario',
        'taxa_comissao',
        'custo_frete',
        'valor_base',
        'frete_por_conta',
        'frete',
        'quantidade_volumes',
        'peso_bruto',
        'prazo_entrega',
        'transportador_id',
        'transportador_nome',
        'etiqueta_nome',
        'etiqueta_endereco',
        'etiqueta_numero',
        'etiqueta_complemento',
        'etiqueta_municipio',
        'etiqueta_uf',
        'etiqueta_cep',
        'etiqueta_bairro',
        'etiqueta_nome_pais',
        'itens',
        'parcelas',
        'volumes',
        'raw_data',
    ];

    protected $casts = [
        'outras_despesas' => 'decimal:2',
        'desconto_valor' => 'decimal:2',
        'total_icms' => 'decimal:2',
        'total_ipi' => 'decimal:2',
        'taxa_comissao' => 'decimal:2',
        'custo_frete' => 'decimal:2',
        'valor_base' => 'decimal:2',
        'frete' => 'decimal:2',
        'peso_bruto' => 'decimal:3',
        'itens' => 'array',
        'parcelas' => 'array',
        'volumes' => 'array',
        'raw_data' => 'array',
    ];

    /**
     * Get the order summary that owns this data
     */
    public function orderSummary(): BelongsTo
    {
        return $this->belongsTo(OrderSummary::class);
    }

    /**
     * Get the invoice associated with this order data
     */
    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class, 'order_summary_id', 'order_summary_id');
    }
}
