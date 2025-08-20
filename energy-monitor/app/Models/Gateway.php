<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Gateway extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'fixed_ip',
        'sim_number',
        'gsm_signal',
        'gnss_location',
        // RTU-specific fields
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
    ];

    protected $casts = [
        'last_system_update' => 'datetime',
        'cpu_load' => 'float',
        'memory_usage' => 'float',
        'uptime_hours' => 'integer',
        'analog_input_voltage' => 'float',
        'di1_status' => 'boolean',
        'di2_status' => 'boolean',
        'do1_status' => 'boolean',
        'do2_status' => 'boolean'
    ];

    public function devices()
    {
        return $this->hasMany(Device::class);
    }

    /**
     * Check if this gateway is an RTU gateway (Teltonika RUT956)
     */
    public function isRTUGateway(): bool
    {
        return $this->gateway_type === 'teltonika_rut956';
    }

    /**
     * Calculate system health score based on various metrics
     */
    public function getSystemHealthScore(): int
    {
        $score = 100;
        
        // Deduct points for high CPU usage
        if ($this->cpu_load > 80) {
            $score -= 20;
        }
        
        // Deduct points for high memory usage
        if ($this->memory_usage > 90) {
            $score -= 30;
        }
        
        // Deduct points for communication issues
        if ($this->communication_status !== 'online') {
            $score -= 50;
        }
        
        return max(0, $score);
    }

    /**
     * Get signal quality status based on RSSI value
     */
    public function getSignalQualityStatus(): string
    {
        if ($this->rssi === null) {
            return 'unknown';
        }
        
        if ($this->rssi > -70) {
            return 'excellent';
        }
        
        if ($this->rssi > -85) {
            return 'good';
        }
        
        if ($this->rssi > -100) {
            return 'fair';
        }
        
        return 'poor';
    }
}
