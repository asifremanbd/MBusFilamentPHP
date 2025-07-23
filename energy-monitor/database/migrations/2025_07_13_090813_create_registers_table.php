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
        Schema::create('registers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->onDelete('cascade');
            $table->string('parameter_name');
            $table->integer('register_address');
            $table->enum('data_type', ['float', 'int', 'uint16', 'uint32']);
            $table->string('unit');
            $table->decimal('scale', 8, 4)->default(1.0);
            $table->string('normal_range')->nullable();
            $table->boolean('critical')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('registers');
    }
};
