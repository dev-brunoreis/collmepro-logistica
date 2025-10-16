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
        Schema::create('bling_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('account_type')->unique(); // 'pro' or 'atacadista'
            $table->text('access_token');
            $table->text('refresh_token')->nullable();
            $table->timestamp('expires_at');
            $table->json('scope')->nullable();
            $table->timestamps();
            
            $table->index(['account_type', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bling_tokens');
    }
};
