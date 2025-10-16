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
            $table->string('bling_data_hash', 32)->nullable()->after('external_updated_at');
            $table->index('bling_data_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_summary', function (Blueprint $table) {
            $table->dropIndex(['bling_data_hash']);
            $table->dropColumn('bling_data_hash');
        });
    }
};

