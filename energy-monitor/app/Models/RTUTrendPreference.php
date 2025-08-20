<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RTUTrendPreference extends Model
{
    use HasFactory;

    protected $table = 'rtu_trend_preferences';

    protected $fillable = [
        'user_id',
        'gateway_id',
        'selected_metrics',
        'time_range',
        'chart_type',
    ];

    protected $casts = [
        'selected_metrics' => 'array',
    ];

    /**
     * Get the user that owns the preference.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the gateway associated with the preference.
     */
    public function gateway(): BelongsTo
    {
        return $this->belongsTo(Gateway::class);
    }

    /**
     * Get the default metrics for RTU trend preferences.
     */
    public static function getDefaultMetrics(): array
    {
        return ['signal_strength'];
    }

    /**
     * Get all available metrics for RTU trend preferences.
     */
    public static function getAvailableMetrics(): array
    {
        return [
            'signal_strength' => 'Signal Strength (RSSI)',
            'cpu_load' => 'CPU Load (%)',
            'memory_usage' => 'Memory Usage (%)',
            'analog_input' => 'Analog Input (V)',
        ];
    }

    /**
     * Get available time ranges for RTU trend preferences.
     */
    public static function getAvailableTimeRanges(): array
    {
        return [
            '1h' => '1 Hour',
            '6h' => '6 Hours',
            '24h' => '24 Hours',
            '7d' => '7 Days',
        ];
    }

    /**
     * Get available chart types for RTU trend preferences.
     */
    public static function getAvailableChartTypes(): array
    {
        return [
            'line' => 'Line Chart',
            'area' => 'Area Chart',
            'bar' => 'Bar Chart',
        ];
    }

    /**
     * Validate selected metrics against available options.
     */
    public function validateSelectedMetrics(): bool
    {
        $availableMetrics = array_keys(self::getAvailableMetrics());
        $selectedMetrics = $this->selected_metrics ?? [];

        foreach ($selectedMetrics as $metric) {
            if (!in_array($metric, $availableMetrics)) {
                return false;
            }
        }

        return !empty($selectedMetrics);
    }

    /**
     * Validate time range against available options.
     */
    public function validateTimeRange(): bool
    {
        $availableRanges = array_keys(self::getAvailableTimeRanges());
        return in_array($this->time_range, $availableRanges);
    }

    /**
     * Validate chart type against available options.
     */
    public function validateChartType(): bool
    {
        $availableTypes = array_keys(self::getAvailableChartTypes());
        return in_array($this->chart_type, $availableTypes);
    }
}