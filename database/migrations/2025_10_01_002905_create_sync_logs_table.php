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
        Schema::create('sync_logs', function (Blueprint $table) {
            $table->id();
            
            // Relacionamento com pedido (opcional, pode ser null para sync gerais)
            $table->foreignId('order_id')->nullable()->constrained()->onDelete('cascade');
            
            // Tipo de sincronização
            $table->enum('sync_type', ['order_import', 'invoice_sync', 'tracking_fetch'])->index();
            
            // Sistema origem (para imports de pedidos)
            $table->string('system')->nullable()->index(); // collmepro, collmepro_atacadista, collme_beauty
            
            // Status da sincronização
            $table->enum('status', ['pending', 'processing', 'success', 'failed', 'skipped'])->default('pending')->index();
            
            // Tentativas
            $table->integer('attempts')->default(0);
            $table->integer('max_attempts')->default(3);
            
            // Mensagens
            $table->text('message')->nullable();
            $table->text('error_message')->nullable();
            $table->json('error_trace')->nullable();
            
            // Dados da sincronização
            $table->json('request_data')->nullable(); // Dados da requisição
            $table->json('response_data')->nullable(); // Dados da resposta
            
            // Timestamps adicionais
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->integer('duration_ms')->nullable(); // Duração em milissegundos
            
            // Metadados adicionais
            $table->string('external_id')->nullable(); // ID externo (ordem no WooCommerce, NFE no Bling, etc)
            $table->string('job_id')->nullable(); // ID do job no Laravel
            $table->json('metadata')->nullable(); // Outros metadados relevantes
            
            $table->timestamps();
            
            // Índices compostos para queries comuns
            $table->index(['sync_type', 'status', 'created_at']);
            $table->index(['order_id', 'sync_type']);
            $table->index(['external_id', 'sync_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sync_logs');
    }
};
