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
        // Create sellers table
        Schema::create('sellers', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('woocommerce_id')->unique();
            $table->string('name');
            $table->string('email')->nullable();
            $table->timestamps();
        });

        // Create pivot table for order_summary and sellers
        Schema::create('order_summary_seller', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_summary_id')->constrained('order_summary')->onDelete('cascade');
            $table->foreignId('seller_id')->constrained('sellers')->onDelete('cascade');
            $table->timestamps();
            
            // Ensure unique combination
            $table->unique(['order_summary_id', 'seller_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_summary_seller');
        Schema::dropIfExists('sellers');
    }
};
