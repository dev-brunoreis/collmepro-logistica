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
        Schema::create('order_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_summary_id')->constrained('order_summary')->onDelete('cascade');
            $table->boolean('picking')->default(false);
            $table->foreignId('picking_marked_by')->nullable()->constrained('users');
            $table->datetime('picking_marked_at')->nullable();
            $table->boolean('packing')->default(false);
            $table->foreignId('packing_marked_by')->nullable()->constrained('users');
            $table->datetime('packing_marked_at')->nullable();
            $table->boolean('picked_up')->default(false);
            $table->foreignId('picked_up_marked_by')->nullable()->constrained('users');
            $table->datetime('picked_up_marked_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_statuses');
    }
};
