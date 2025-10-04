<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    use HasFactory;

    protected $table = 'invoices';

    protected $fillable = [
        'order_summary_id',
        'bling_id',
        'tipo',
        'situacao',
        'numero',
        'data_emissao',
        'data_operacao',
        'chave_acesso',
        'valor_nota',
        'valor_frete',
        'xml',
        'link_danfe',
        'link_pdf',
        'optante_simples_nacional',
        'numero_pedido_loja',
        'serie',
        'contato_id',
        'contato_nome',
        'contato_documento',
        'contato_ie',
        'contato_rg',
        'contato_telefone',
        'contato_email',
        'contato_endereco',
        'contato_numero',
        'contato_complemento',
        'contato_bairro',
        'contato_cep',
        'contato_municipio',
        'contato_uf',
        'contato_pais',
        'natureza_operacao_id',
        'loja_id',
        'vendedor_id',
        'frete_por_conta',
        'transportador_nome',
        'transportador_documento',
        'etiqueta_nome',
        'etiqueta_endereco',
        'etiqueta_numero',
        'etiqueta_complemento',
        'etiqueta_municipio',
        'etiqueta_uf',
        'etiqueta_cep',
        'etiqueta_bairro',
        'itens',
        'parcelas',
        'volumes',
        'raw_data',
    ];

    protected $casts = [
        'data_emissao' => 'datetime',
        'data_operacao' => 'datetime',
        'valor_nota' => 'decimal:2',
        'valor_frete' => 'decimal:2',
        'optante_simples_nacional' => 'boolean',
        'itens' => 'array',
        'parcelas' => 'array',
        'volumes' => 'array',
        'raw_data' => 'array',
    ];

    /**
     * Get the order summary that owns this invoice
     */
    public function orderSummary(): BelongsTo
    {
        return $this->belongsTo(OrderSummary::class);
    }

    /**
     * Get the tracking records for this invoice
     */
    public function tracking()
    {
        return $this->hasMany(Tracking::class);
    }
}
