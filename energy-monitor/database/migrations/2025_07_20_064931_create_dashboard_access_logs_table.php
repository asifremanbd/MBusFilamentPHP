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
        Schema::create('dashboard_access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('dashboard_type', 50);
            $table->foreignId('gateway_id')->nullable()->constrained()->onDelete('set null');
            $table->string('widget_accessed', 100)->nullable();
            $table->boolean('access_granted');
            $table->string('ip_address', 45);
            $table->text('user_agent')->nullable();
            $table->timestamp('accessed_at')->useCurrent();
            
            $table->index(['user_id', 'accessed_at'], 'idx_user_access');
            $table->index(['gateway_id', 'accessed_at'], 'idx_gateway_access');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dashboard_access_logs');
    }
};
