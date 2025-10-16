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
        Schema::create('failed_sync_jobs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_summary_id');
            $table->string('job_type', 50); // invoice_sync, tracking_extraction, seller_sync
            $table->text('error');
            $table->integer('attempts')->default(0);
            $table->json('payload')->nullable();
            $table->timestamp('failed_at');
            $table->boolean('resolved')->default(false);
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();

            $table->index('order_summary_id');
            $table->index('job_type');
            $table->index('resolved');
            $table->index('failed_at');

            $table->foreign('order_summary_id')
                ->references('id')
                ->on('order_summary')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('failed_sync_jobs');
    }
};
