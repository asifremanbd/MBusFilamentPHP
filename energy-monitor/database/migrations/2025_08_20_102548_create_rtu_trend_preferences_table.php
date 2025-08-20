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
        Schema::create('rtu_trend_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('gateway_id')->constrained()->onDelete('cascade');
            $table->json('selected_metrics')->default('["signal_strength"]');
            $table->string('time_range', 10)->default('24h');
            $table->string('chart_type', 20)->default('line');
            $table->timestamps();
            
            $table->unique(['user_id', 'gateway_id'], 'unique_user_gateway_prefs');
            $table->index(['user_id', 'gateway_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rtu_trend_preferences');
    }
};