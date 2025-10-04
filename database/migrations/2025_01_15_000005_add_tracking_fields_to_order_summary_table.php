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
            $table->string('tracking_code')->nullable()->after('external_updated_at');
            $table->string('logistics_provider')->nullable()->after('tracking_code');
            $table->string('service_type')->nullable()->after('logistics_provider');
            $table->decimal('volume', 8, 2)->nullable()->after('service_type');
            $table->decimal('weight', 8, 2)->nullable()->after('volume');
            
            // Indexes
            $table->index('tracking_code');
            $table->index('logistics_provider');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_summary', function (Blueprint $table) {
            $table->dropIndex(['tracking_code']);
            $table->dropIndex(['logistics_provider']);
            
            $table->dropColumn([
                'tracking_code',
                'logistics_provider',
                'service_type',
                'volume',
                'weight',
            ]);
        });
    }
};
