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
            $table->json('raw_order_data')->nullable()->after('weight');
            $table->json('raw_invoice_data')->nullable()->after('raw_order_data');
            $table->json('raw_tracking_data')->nullable()->after('raw_invoice_data');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_summary', function (Blueprint $table) {
            $table->dropColumn([
                'raw_order_data',
                'raw_invoice_data',
                'raw_tracking_data',
            ]);
        });
    }
};
