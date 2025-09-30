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
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('danfe_url')->nullable()->after('invoice_pdf_url');
            $table->string('access_key')->nullable()->after('danfe_url');
            $table->datetime('operation_date')->nullable()->after('issued_at');
            $table->integer('status')->nullable()->after('operation_date');
            $table->string('invoice_type')->nullable()->after('status');
            $table->decimal('invoice_value', 10, 2)->nullable()->after('invoice_type');
            $table->decimal('freight_value', 10, 2)->nullable()->after('invoice_value');
            $table->integer('series')->nullable()->after('freight_value');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn([
                'danfe_url', 
                'access_key', 
                'operation_date', 
                'status', 
                'invoice_type', 
                'invoice_value', 
                'freight_value', 
                'series'
            ]);
        });
    }
};