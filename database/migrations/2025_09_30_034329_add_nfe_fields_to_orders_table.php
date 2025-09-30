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
        Schema::table('orders', function (Blueprint $table) {
            $table->string('nfe_access_key')->nullable()->after('nfe_pdf_url');
            $table->datetime('nfe_issue_date')->nullable()->after('nfe_access_key');
            $table->decimal('nfe_value', 10, 2)->nullable()->after('nfe_issue_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['nfe_access_key', 'nfe_issue_date', 'nfe_value']);
        });
    }
};