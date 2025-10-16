<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('order_data', function (Blueprint $table) {
            $table->id();
            
            // Foreign key para order_summary
            $table->foreignId('order_summary_id')->constrained('order_summary')->onDelete('cascade');
            
            // Dados adicionais do pedido completo
            $table->string('numero_pedido_compra')->nullable(); // numeroPedidoCompra
            $table->decimal('outras_despesas', 10, 2)->nullable(); // outrasDespesas
            $table->text('observacoes')->nullable(); // observacoes
            $table->text('observacoes_internas')->nullable(); // observacoesInternas
            
            // Desconto
            $table->decimal('desconto_valor', 10, 2)->nullable(); // desconto.valor
            $table->string('desconto_unidade')->nullable(); // desconto.unidade
            
            // Categoria
            $table->bigInteger('categoria_id')->nullable(); // categoria.id
            
            // Nota Fiscal
            $table->bigInteger('nota_fiscal_id')->nullable(); // notaFiscal.id
            
            // Tributação
            $table->decimal('total_icms', 10, 2)->nullable(); // tributacao.totalICMS
            $table->decimal('total_ipi', 10, 2)->nullable(); // tributacao.totalIPI
            
            // Vendedor
            $table->bigInteger('vendedor_id')->nullable(); // vendedor.id
            
            // Intermediador
            $table->string('intermediador_cnpj')->nullable(); // intermediador.cnpj
            $table->string('intermediador_nome_usuario')->nullable(); // intermediador.nomeUsuario
            
            // Taxas
            $table->decimal('taxa_comissao', 10, 2)->nullable(); // taxas.taxaComissao
            $table->decimal('custo_frete', 10, 2)->nullable(); // taxas.custoFrete
            $table->decimal('valor_base', 10, 2)->nullable(); // taxas.valorBase
            
            // Transporte
            $table->integer('frete_por_conta')->nullable(); // transporte.fretePorConta
            $table->decimal('frete', 10, 2)->nullable(); // transporte.frete
            $table->integer('quantidade_volumes')->nullable(); // transporte.quantidadeVolumes
            $table->decimal('peso_bruto', 10, 3)->nullable(); // transporte.pesoBruto
            $table->integer('prazo_entrega')->nullable(); // transporte.prazoEntrega
            
            // Dados do transportador
            $table->bigInteger('transportador_id')->nullable(); // transporte.contato.id
            $table->string('transportador_nome')->nullable(); // transporte.contato.nome
            
            // Etiqueta do transporte
            $table->string('etiqueta_nome')->nullable(); // transporte.etiqueta.nome
            $table->string('etiqueta_endereco')->nullable(); // transporte.etiqueta.endereco
            $table->string('etiqueta_numero')->nullable(); // transporte.etiqueta.numero
            $table->string('etiqueta_complemento')->nullable(); // transporte.etiqueta.complemento
            $table->string('etiqueta_municipio')->nullable(); // transporte.etiqueta.municipio
            $table->string('etiqueta_uf', 2)->nullable(); // transporte.etiqueta.uf
            $table->string('etiqueta_cep', 9)->nullable(); // transporte.etiqueta.cep
            $table->string('etiqueta_bairro')->nullable(); // transporte.etiqueta.bairro
            $table->string('etiqueta_nome_pais')->nullable(); // transporte.etiqueta.nomePais
            
            // Itens do pedido (JSON)
            $table->json('itens')->nullable(); // Array completo de itens
            
            // Parcelas (JSON)
            $table->json('parcelas')->nullable(); // Array completo de parcelas
            
            // Volumes (JSON)
            $table->json('volumes')->nullable(); // Array completo de volumes
            
            // Dados brutos completos (JSON) - para exibir na tela
            $table->json('raw_data')->nullable(); // Dados completos da API
            
            $table->timestamps();
            
            // Índices
            $table->index(['order_summary_id']);
            $table->index(['nota_fiscal_id']);
            $table->index(['categoria_id']);
            $table->index(['vendedor_id']);
            $table->index(['transportador_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_data');
    }
};
