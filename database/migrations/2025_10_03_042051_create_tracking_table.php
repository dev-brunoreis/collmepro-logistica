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
        Schema::create('tracking', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->onDelete('cascade');
            $table->bigInteger('volume_id')->nullable(); // ID do volume da API Bling
            $table->bigInteger('pedido_venda_id')->nullable();
            $table->bigInteger('nota_fiscal_id')->nullable();
            $table->bigInteger('servico_id')->nullable();
            $table->bigInteger('embalagem_id')->nullable();
            
            // Rastreamento
            $table->string('codigo_rastreamento')->nullable(); // Código de rastreamento (ex: EC272330554BR)
            $table->string('descricao')->nullable(); // Status atual (ex: "Criado")
            $table->integer('situacao')->nullable(); // Código da situação
            $table->string('origem')->nullable(); // Origem do envio
            $table->string('destino')->nullable(); // Destino do envio
            $table->timestamp('ultima_alteracao')->nullable(); // Última atualização do status
            $table->string('url_rastreamento')->nullable(); // URL para rastreamento
            
            // Dimensões
            $table->decimal('peso', 8, 2)->nullable();
            $table->decimal('altura', 8, 2)->nullable();
            $table->decimal('largura', 8, 2)->nullable();
            $table->decimal('comprimento', 8, 2)->nullable();
            $table->decimal('diametro', 8, 2)->nullable();
            
            // Informações de entrega
            $table->date('data_saida')->nullable();
            $table->integer('prazo_entrega_previsto')->nullable(); // Em dias
            $table->decimal('frete_previsto', 10, 2)->nullable();
            $table->decimal('valor_declarado', 10, 2)->nullable();
            $table->boolean('aviso_recebimento')->default(false);
            $table->boolean('mao_propria')->default(false);
            
            // Controle de sincronização
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamp('next_sync_at')->nullable();
            $table->integer('sync_attempts')->default(0);
            $table->text('last_error')->nullable();
            
            // Dados brutos da API
            $table->json('raw_data')->nullable();
            
            $table->timestamps();
            
            // Índices
            $table->index(['invoice_id', 'volume_id']);
            $table->index('codigo_rastreamento');
            $table->index('situacao');
            $table->index('last_sync_at');
            $table->index('next_sync_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tracking');
    }
};
