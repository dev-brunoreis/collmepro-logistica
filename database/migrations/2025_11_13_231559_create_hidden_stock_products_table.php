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
        Schema::create('hidden_stock_products', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('product_id')->index();
            $table->string('source'); // 'Pro' or 'Atacadista'
            $table->string('product_name')->nullable();
            $table->string('product_code')->nullable();
            $table->timestamps();
            
            // Unique constraint to prevent duplicate product_id + source combinations
            $table->unique(['product_id', 'source']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hidden_stock_products');
    }
};
