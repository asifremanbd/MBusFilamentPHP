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
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('email');
            $table->boolean('email_notifications')->default(true)->after('phone');
            $table->boolean('sms_notifications')->default(false)->after('email_notifications');
            $table->boolean('notification_critical_only')->default(false)->after('sms_notifications');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'phone',
                'email_notifications',
                'sms_notifications',
                'notification_critical_only'
            ]);
        });
    }
};
