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
        Schema::dropIfExists('labels');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // We don't restore the labels table as it's being permanently removed
        // The original create_labels_table migration files are preserved for history
    }
};