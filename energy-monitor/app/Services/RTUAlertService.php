<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\Gateway;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class RTUAlertService
{
    /**
     * Get grouped alerts for an RTU gateway
     */
    public function getGroupedAlerts(Gateway $gateway): array
    {
        $alerts = Alert::whereHas('device', function ($query) use ($gateway) {
            $query->where('gateway_id', $gateway->id);
        })
        ->where('resolved', false)
        ->orderBy('created_at', 'desc')
        ->get();

        $groupedAlerts = $this->groupSimilarAlerts($alerts);
        $filteredAlerts = $this->filterOffHoursAlerts($groupedAlerts);

        return [
            'critical_count' => $filteredAlerts->where('severity', 'critical')->count(),
            'warning_count' => $filteredAlerts->where('severity', 'warning')->count(),
            'info_count' => $filteredAlerts->where('severity', 'info')->count(),
            'grouped_alerts' => $filteredAlerts->take(10)->values(),
            'has_alerts' => $filteredAlerts->isNotEmpty(),
            'status_summary' => $this->getAlertStatusSummary($filteredAlerts)
        ];
    }

    /**
     * Get filtered alerts based on criteria
     */
    public function getFilteredAlerts(Gateway $gateway, array $filters): EloquentCollection
    {
        $query = Alert::whereHas('device', function ($query) use ($gateway) {
            $query->where('gateway_id', $gateway->id);
        });

        // Filter by severity
        if (isset($filters['severity']) && !empty($filters['severity'])) {
            $query->whereIn('severity', $filters['severity']);
        }

        // Filter by specific device IDs
        if (isset($filters['device_ids']) && !empty($filters['device_ids'])) {
            $query->whereIn('device_id', $filters['device_ids']);
        }

        // Filter by time range
        if (isset($filters['time_range'])) {
            $startTime = match($filters['time_range']) {
                'last_hour' => now()->subHour(),
                'last_day' => now()->subDay(),
                'last_week' => now()->subWeek(),
                'custom' => isset($filters['start_date']) ? Carbon::parse($filters['start_date']) : now()->subDay(),
                default => now()->subDay()
            };

            $endTime = isset($filters['end_date']) ? Carbon::parse($filters['end_date']) : now();
            $query->whereBetween('created_at', [$startTime, $endTime]);
        }

        // Filter by resolved status
        if (isset($filters['resolved'])) {
            $query->where('resolved', $filters['resolved']);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Group similar alerts to consolidate repeated notifications
     */
    protected function groupSimilarAlerts($alerts): Collection
    {
        $grouped = $alerts->groupBy(function ($alert) {
            // Group by alert type (normalize parameter names for RTU-specific alerts)
            return $this->normalizeAlertType($alert->parameter_name);
        });

        return $grouped->map(function ($alertGroup, $type) {
            $latest = $alertGroup->first();
            $count = $alertGroup->count();
            
            return (object) [
                'type' => $type,
                'parameter_name' => $latest->parameter_name,
                'message' => $latest->message,
                'severity' => $this->getHighestSeverity($alertGroup),
                'count' => $count,
                'latest_timestamp' => $latest->created_at,
                'first_occurrence' => $alertGroup->last()->created_at,
                'is_grouped' => $count > 1,
                'device_id' => $latest->device_id,
                'latest_value' => $latest->value,
                'alert_ids' => $alertGroup->pluck('id')->toArray()
            ];
        });
    }

    /**
     * Filter off-hours alerts to reduce noise during non-business hours
     */
    protected function filterOffHoursAlerts($groupedAlerts): Collection
    {
        $currentHour = now()->hour;
        $isBusinessHours = $currentHour >= 8 && $currentHour <= 18;

        if ($isBusinessHours) {
            return $groupedAlerts;
        }

        // During off-hours, only show critical alerts in main view
        // Move non-critical alerts to low-priority log
        $criticalAlerts = $groupedAlerts->filter(function ($alert) {
            return $alert->severity === 'critical';
        });

        // Log non-critical alerts for off-hours review
        $nonCriticalAlerts = $groupedAlerts->filter(function ($alert) {
            return $alert->severity !== 'critical';
        });

        if ($nonCriticalAlerts->isNotEmpty()) {
            Log::info('Off-hours non-critical alerts moved to low-priority log', [
                'alert_count' => $nonCriticalAlerts->count(),
                'alert_types' => $nonCriticalAlerts->pluck('type')->toArray(),
                'timestamp' => now()->toISOString()
            ]);
        }

        return $criticalAlerts;
    }

    /**
     * Get simplified alert status summary
     */
    public function getAlertStatusSummary($alerts): string
    {
        $criticalCount = $alerts->where('severity', 'critical')->count();
        $warningCount = $alerts->where('severity', 'warning')->count();

        if ($criticalCount > 0) {
            return $criticalCount === 1 ? '1 Critical Alert' : "{$criticalCount} Critical Alerts";
        }

        if ($warningCount > 0) {
            return $warningCount === 1 ? '1 Warning' : "{$warningCount} Warnings";
        }

        return 'All Systems OK';
    }

    /**
     * Normalize alert types for RTU-specific grouping
     */
    protected function normalizeAlertType(string $parameterName): string
    {
        // Normalize common RTU alert types
        $normalizedTypes = [
            'router_uptime' => 'Router Uptime',
            'uptime' => 'Router Uptime',
            'system_uptime' => 'Router Uptime',
            'connection_state' => 'Connection State',
            'connection_status' => 'Connection State',
            'network_status' => 'Connection State',
            'gsm_signal' => 'GSM Signal',
            'signal_strength' => 'GSM Signal',
            'rssi' => 'GSM Signal',
            'rsrp' => 'GSM Signal',
            'rsrq' => 'GSM Signal',
            'sinr' => 'GSM Signal',
            'cpu_load' => 'System Performance',
            'memory_usage' => 'System Performance',
            'system_load' => 'System Performance',
            'digital_input' => 'I/O Status',
            'digital_output' => 'I/O Status',
            'analog_input' => 'I/O Status',
            'di1' => 'I/O Status',
            'di2' => 'I/O Status',
            'do1' => 'I/O Status',
            'do2' => 'I/O Status',
            'wan_ip' => 'Network Configuration',
            'sim_status' => 'SIM Card Status',
            'sim_iccid' => 'SIM Card Status',
            'sim_operator' => 'SIM Card Status'
        ];

        $lowerParam = strtolower($parameterName);
        
        // Check for exact matches first
        if (isset($normalizedTypes[$lowerParam])) {
            return $normalizedTypes[$lowerParam];
        }

        // Check for partial matches
        foreach ($normalizedTypes as $key => $value) {
            if (str_contains($lowerParam, $key) || str_contains($key, $lowerParam)) {
                return $value;
            }
        }

        // Default to the original parameter name if no match found
        return ucwords(str_replace('_', ' ', $parameterName));
    }

    /**
     * Get the highest severity from a group of alerts
     */
    protected function getHighestSeverity($alertGroup): string
    {
        $severityOrder = ['info' => 1, 'warning' => 2, 'critical' => 3];
        
        $highestSeverity = 'info';
        $highestValue = 0;

        foreach ($alertGroup as $alert) {
            $severityValue = $severityOrder[$alert->severity] ?? 0;
            if ($severityValue > $highestValue) {
                $highestValue = $severityValue;
                $highestSeverity = $alert->severity;
            }
        }

        return $highestSeverity;
    }

    /**
     * Get alert statistics for RTU dashboard
     */
    public function getAlertStats(Gateway $gateway): array
    {
        $baseQuery = Alert::whereHas('device', function ($query) use ($gateway) {
            $query->where('gateway_id', $gateway->id);
        });

        $totalAlerts = (clone $baseQuery)->count();
        $activeAlerts = (clone $baseQuery)->where('resolved', false)->count();
        $criticalAlerts = (clone $baseQuery)
            ->where('resolved', false)
            ->where('severity', 'critical')
            ->count();
        $todayAlerts = (clone $baseQuery)->whereDate('created_at', today())->count();

        return [
            'total' => $totalAlerts,
            'active' => $activeAlerts,
            'critical' => $criticalAlerts,
            'today' => $todayAlerts,
            'status_summary' => $this->getAlertStatusSummary(
                (clone $baseQuery)->where('resolved', false)->get()
            )
        ];
    }

    /**
     * Resolve multiple alerts by IDs
     */
    public function resolveAlerts(array $alertIds, int $userId): array
    {
        $resolved = [];
        $failed = [];

        foreach ($alertIds as $alertId) {
            $alert = Alert::find($alertId);
            
            if (!$alert) {
                $failed[] = $alertId;
                continue;
            }

            try {
                $alert->update([
                    'resolved' => true,
                    'resolved_by' => $userId,
                    'resolved_at' => now()
                ]);

                $resolved[] = $alertId;

                Log::info("RTU alert resolved", [
                    'alert_id' => $alertId,
                    'resolved_by' => $userId,
                    'parameter_name' => $alert->parameter_name,
                    'device_id' => $alert->device_id
                ]);
            } catch (\Exception $e) {
                $failed[] = $alertId;
                Log::error("Failed to resolve RTU alert", [
                    'alert_id' => $alertId,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return [
            'resolved' => $resolved,
            'failed' => $failed,
            'resolved_count' => count($resolved),
            'failed_count' => count($failed)
        ];
    }
}