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
        Schema::table('order_statuses', function (Blueprint $table) {
            // Drop existing foreign key constraints if they exist
            try {
                $table->dropForeign(['picking_marked_by']);
            } catch (\Exception $e) {
                // Column might not exist
            }
            try {
                $table->dropForeign(['packing_marked_by']);
            } catch (\Exception $e) {
                // Column might not exist
            }
            try {
                $table->dropForeign(['picked_up_marked_by']);
            } catch (\Exception $e) {
                // Column might not exist
            }
            
            // Drop existing columns if they exist
            try {
                $table->dropColumn(['picking_marked_by', 'packing_marked_by', 'picked_up_marked_by']);
            } catch (\Exception $e) {
                // Columns might not exist
            }
            
            // Add new columns
            $table->string('picking_marked_by')->nullable()->after('picking');
            $table->string('packing_marked_by')->nullable()->after('packing');
            $table->string('picked_up_marked_by')->nullable()->after('picked_up');
            
            // Add unique constraint
            $table->unique('order_summary_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_statuses', function (Blueprint $table) {
            // Drop new constraints and columns
            $table->dropUnique(['order_summary_id']);
            $table->dropForeign(['order_summary_id']);
            $table->dropColumn(['order_summary_id', 'picking_marked_by', 'packing_marked_by', 'picked_up_marked_by']);
            
            // Restore original columns
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('picking_marked_by')->nullable()->constrained('users');
            $table->foreignId('packing_marked_by')->nullable()->constrained('users');
            $table->foreignId('picked_up_marked_by')->nullable()->constrained('users');
        });
    }
};
