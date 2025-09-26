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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('system');
            $table->string('order_number');
            $table->string('customer_name');
            $table->string('seller_name');
            $table->datetime('order_date');
            $table->string('nfe_number')->nullable();
            $table->string('nfe_pdf_url')->nullable();
            $table->string('tracking_code')->nullable();
            $table->string('logistics_provider')->nullable();
            $table->string('tracking_status')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
