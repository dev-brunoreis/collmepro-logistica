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
        Schema::create('order_histories', function (Blueprint $table) {
            $table->id();
            
            // Foreign key para order_summary
            $table->foreignId('order_summary_id')->constrained('order_summary')->onDelete('cascade');
            
            // Tipo de evento
            $table->string('event_type'); // order_imported, status_picking, invoice_synced, etc.
            
            // Descrição do evento
            $table->text('event_description')->nullable();
            
            // Usuário que executou a ação (nullable para eventos automáticos)
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            
            // Metadados adicionais (JSON)
            $table->json('metadata')->nullable();
            
            // Data de criação (usar created_at como timestamp do evento)
            $table->timestamps();
            
            // Índices
            $table->index(['order_summary_id', 'event_type']);
            $table->index('created_at');
            $table->index('event_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_histories');
    }
};
