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
        // Check if labels table exists and has the duplicate index
        if (Schema::hasTable('labels')) {
            Schema::table('labels', function (Blueprint $table) {
                // Drop the duplicate index if it exists
                $table->dropIndex('labels_issue_date_index');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Re-add the index if needed
        if (Schema::hasTable('labels')) {
            Schema::table('labels', function (Blueprint $table) {
                $table->index('issue_date');
            });
        }
    }
};