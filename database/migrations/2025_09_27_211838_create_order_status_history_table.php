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
        Schema::create('order_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->string('status_type'); // 'picking', 'packing', 'picked_up'
            $table->boolean('status_value'); // true = marked, false = unmarked
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamp('changed_at');
            $table->timestamps();
            
            $table->index(['order_id', 'status_type']);
            $table->index('changed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_status_history');
    }
};