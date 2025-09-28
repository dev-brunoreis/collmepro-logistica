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
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // e.g., 'orders.view', 'orders.edit', 'users.manage'
            $table->string('display_name'); // e.g., 'Visualizar Pedidos', 'Editar Pedidos'
            $table->text('description')->nullable(); // Descrição detalhada da permissão
            $table->string('category')->default('general'); // e.g., 'orders', 'users', 'system'
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};