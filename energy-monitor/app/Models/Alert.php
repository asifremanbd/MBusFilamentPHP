<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Alert extends Model
{
    use HasFactory;

    protected $fillable = [
        'device_id',
        'parameter_name',
        'value',
        'severity',
        'timestamp',
        'resolved',
        'resolved_by',
        'resolved_at',
        'message',
    ];

    protected $casts = [
        'value' => 'decimal:4',
        'timestamp' => 'datetime',
        'resolved' => 'boolean',
        'resolved_at' => 'datetime',
    ];

    public function device()
    {
        return $this->belongsTo(Device::class);
    }

    public function resolvedBy()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function gateway()
    {
        return $this->hasOneThrough(Gateway::class, Device::class, 'id', 'id', 'device_id', 'gateway_id');
    }

    /**
     * Get the severity color for UI display
     */
    public function getSeverityColorAttribute(): string
    {
        return match($this->severity) {
            'critical' => 'danger',
            'warning' => 'warning',
            'info' => 'info',
            default => 'secondary'
        };
    }

    /**
     * Check if alert is active (not resolved)
     */
    public function isActive(): bool
    {
        return !$this->resolved;
    }
}
