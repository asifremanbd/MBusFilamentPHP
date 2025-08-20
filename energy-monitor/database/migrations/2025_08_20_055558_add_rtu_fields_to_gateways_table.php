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
        Schema::table('gateways', function (Blueprint $table) {
            // RTU Gateway Type
            $table->string('gateway_type', 50)->default('generic')->after('gnss_location');
            
            // Network Information
            $table->string('wan_ip', 45)->nullable()->after('gateway_type');
            $table->string('sim_iccid', 50)->nullable()->after('wan_ip');
            $table->string('sim_apn', 100)->nullable()->after('sim_iccid');
            $table->string('sim_operator', 100)->nullable()->after('sim_apn');
            
            // System Health Metrics
            $table->decimal('cpu_load', 5, 2)->nullable()->after('sim_operator');
            $table->decimal('memory_usage', 5, 2)->nullable()->after('cpu_load');
            $table->integer('uptime_hours')->nullable()->after('memory_usage');
            
            // Signal Quality Metrics
            $table->integer('rssi')->nullable()->after('uptime_hours');
            $table->integer('rsrp')->nullable()->after('rssi');
            $table->integer('rsrq')->nullable()->after('rsrp');
            $table->integer('sinr')->nullable()->after('rsrq');
            
            // Digital I/O Status
            $table->boolean('di1_status')->default(false)->after('sinr');
            $table->boolean('di2_status')->default(false)->after('di1_status');
            $table->boolean('do1_status')->default(false)->after('di2_status');
            $table->boolean('do2_status')->default(false)->after('do1_status');
            
            // Analog Input
            $table->decimal('analog_input_voltage', 6, 3)->nullable()->after('do2_status');
            
            // System Status
            $table->timestamp('last_system_update')->nullable()->after('analog_input_voltage');
            $table->enum('communication_status', ['online', 'warning', 'offline'])->default('offline')->after('last_system_update');
            
            // Indexes for performance
            $table->index('gateway_type');
            $table->index('communication_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gateways', function (Blueprint $table) {
            $table->dropIndex(['gateway_type']);
            $table->dropIndex(['communication_status']);
            
            $table->dropColumn([
                'gateway_type',
                'wan_ip',
                'sim_iccid',
                'sim_apn',
                'sim_operator',
                'cpu_load',
                'memory_usage',
                'uptime_hours',
                'rssi',
                'rsrp',
                'rsrq',
                'sinr',
                'di1_status',
                'di2_status',
                'do1_status',
                'do2_status',
                'analog_input_voltage',
                'last_system_update',
                'communication_status'
            ]);
        });
    }
};
