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
        Schema::table('order_summary', function (Blueprint $table) {
            // Drop redundant raw data columns
            // This data already exists in invoices.raw_data and tracking.raw_data
            $table->dropColumn(['raw_invoice_data', 'raw_tracking_data']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_summary', function (Blueprint $table) {
            // Restore columns if needed for rollback
            $table->json('raw_invoice_data')->nullable()->after('raw_order_data');
            $table->json('raw_tracking_data')->nullable()->after('raw_invoice_data');
        });
    }
};
