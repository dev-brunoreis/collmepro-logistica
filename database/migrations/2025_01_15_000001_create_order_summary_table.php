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
        Schema::create('order_summary', function (Blueprint $table) {
            $table->id();
            
            // Dados básicos do pedido (vem de /pedidos/vendas)
            $table->bigInteger('bling_id')->unique(); // ID do pedido no Bling
            $table->string('numero'); // Número do pedido
            $table->string('numero_loja')->nullable(); // Número da loja
            $table->datetime('data'); // Data do pedido
            $table->datetime('data_saida')->nullable(); // Data de saída
            $table->datetime('data_prevista')->nullable(); // Data prevista
            $table->integer('total_produtos')->default(0); // Total de produtos
            $table->decimal('total', 10, 2)->default(0); // Valor total
            
            // Dados do contato/cliente
            $table->bigInteger('contato_id')->nullable(); // ID do contato no Bling
            $table->string('contato_nome')->nullable(); // Nome do contato
            $table->string('contato_tipo_pessoa')->nullable(); // F ou J
            $table->string('contato_documento')->nullable(); // CPF ou CNPJ
            
            // Dados da situação
            $table->bigInteger('situacao_id')->nullable(); // ID da situação no Bling
            $table->integer('situacao_valor')->nullable(); // Valor da situação (1, 2, 3, etc.)
            
            // Dados da loja
            $table->bigInteger('loja_id')->nullable(); // ID da loja no Bling
            
            // Campos de controle
            $table->string('source')->default('bling'); // Fonte dos dados
            $table->boolean('data_processed')->default(false); // Flag para controlar se já foi processado para order_data
            $table->datetime('external_updated_at')->nullable(); // Data de atualização no sistema externo
            $table->timestamps();
            
            // Índices
            $table->index(['bling_id']);
            $table->index(['numero']);
            $table->index(['data']);
            $table->index(['situacao_valor']);
            $table->index(['source']);
            $table->index(['data_processed']); // Para buscar orders não processados
            $table->index(['external_updated_at']); // Para sync incremental
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_summary');
    }
};
