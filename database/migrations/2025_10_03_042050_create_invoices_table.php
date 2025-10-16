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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_summary_id')->constrained('order_summary')->onDelete('cascade');
            $table->bigInteger('bling_id')->unique();
            $table->integer('tipo')->nullable();
            $table->integer('situacao')->nullable();
            $table->string('numero')->nullable();
            $table->timestamp('data_emissao')->nullable();
            $table->timestamp('data_operacao')->nullable();
            $table->string('chave_acesso')->nullable();
            $table->decimal('valor_nota', 10, 2)->nullable();
            $table->decimal('valor_frete', 10, 2)->nullable();
            $table->text('xml')->nullable();
            $table->string('link_danfe')->nullable();
            $table->string('link_pdf')->nullable();
            $table->boolean('optante_simples_nacional')->default(false);
            $table->string('numero_pedido_loja')->nullable();
            $table->integer('serie')->nullable();
            
            // Contato
            $table->bigInteger('contato_id')->nullable();
            $table->string('contato_nome')->nullable();
            $table->string('contato_documento')->nullable();
            $table->string('contato_ie')->nullable();
            $table->string('contato_rg')->nullable();
            $table->string('contato_telefone')->nullable();
            $table->string('contato_email')->nullable();
            
            // Endereço do contato
            $table->string('contato_endereco')->nullable();
            $table->string('contato_numero')->nullable();
            $table->string('contato_complemento')->nullable();
            $table->string('contato_bairro')->nullable();
            $table->string('contato_cep')->nullable();
            $table->string('contato_municipio')->nullable();
            $table->string('contato_uf')->nullable();
            $table->string('contato_pais')->nullable();
            
            // Natureza da operação e loja
            $table->bigInteger('natureza_operacao_id')->nullable();
            $table->bigInteger('loja_id')->nullable();
            $table->bigInteger('vendedor_id')->nullable();
            
            // Transporte
            $table->integer('frete_por_conta')->nullable();
            $table->string('transportador_nome')->nullable();
            $table->string('transportador_documento')->nullable();
            
            // Etiqueta de transporte
            $table->string('etiqueta_nome')->nullable();
            $table->string('etiqueta_endereco')->nullable();
            $table->string('etiqueta_numero')->nullable();
            $table->string('etiqueta_complemento')->nullable();
            $table->string('etiqueta_municipio')->nullable();
            $table->string('etiqueta_uf')->nullable();
            $table->string('etiqueta_cep')->nullable();
            $table->string('etiqueta_bairro')->nullable();
            
            // Arrays JSON
            $table->json('itens')->nullable();
            $table->json('parcelas')->nullable();
            $table->json('volumes')->nullable();
            $table->json('raw_data')->nullable();
            
            $table->timestamps();
            
            $table->index(['order_summary_id', 'bling_id']);
            $table->index('numero');
            $table->index('data_emissao');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
