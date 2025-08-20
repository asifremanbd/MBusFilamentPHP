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
        Schema::create('rtu_dashboard_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('section_name', 50);
            $table->boolean('is_collapsed')->default(false);
            $table->integer('display_order')->default(0);
            $table->timestamps();
            
            $table->unique(['user_id', 'section_name'], 'unique_user_section');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rtu_dashboard_sections');
    }
};
