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
        Schema::create('labels', function (Blueprint $table) {
            $table->id();
            
            // Relationship with order
            $table->foreignId('order_summary_id')->nullable()->constrained('order_summary')->onDelete('cascade');
            
            // Bling label data
            $table->bigInteger('bling_label_id')->unique(); // ID from Bling API
            $table->string('tracking_code')->nullable()->index(); // Tracking code
            $table->enum('format', ['PDF', 'ZPL'])->default('PDF'); // Label format
            $table->timestamp('issue_date')->nullable(); // When label was issued
            $table->text('link')->nullable(); // URL to download label
            $table->text('observation')->nullable(); // Notes/observations
            
            // Raw data from API for audit trail
            $table->json('raw_data')->nullable();
            
            $table->timestamps();
            
            // Indexes for better performance
            $table->index(['order_summary_id', 'issue_date']);
            $table->index('issue_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('labels');
    }
};

