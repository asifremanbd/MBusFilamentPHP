<?php

namespace App\Services;

use App\Models\Gateway;
use App\Models\RTUTrendPreference;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class RTUDashboardConfigService
{
    /**
     * Get user's RTU trend preferences for a specific gateway.
     */
    public function getTrendPreferences(User $user, Gateway $gateway): RTUTrendPreference
    {
        $preferences = RTUTrendPreference::where('user_id', $user->id)
            ->where('gateway_id', $gateway->id)
            ->first();

        if (!$preferences) {
            $preferences = $this->createDefaultPreferences($user, $gateway);
        }

        return $preferences;
    }

    /**
     * Update user's RTU trend preferences.
     */
    public function updateTrendPreferences(User $user, Gateway $gateway, array $data): RTUTrendPreference
    {
        $this->validatePreferenceData($data);

        $preferences = RTUTrendPreference::updateOrCreate(
            [
                'user_id' => $user->id,
                'gateway_id' => $gateway->id,
            ],
            [
                'selected_metrics' => $data['selected_metrics'] ?? RTUTrendPreference::getDefaultMetrics(),
                'time_range' => $data['time_range'] ?? '24h',
                'chart_type' => $data['chart_type'] ?? 'line',
            ]
        );

        Log::info('RTU trend preferences updated', [
            'user_id' => $user->id,
            'gateway_id' => $gateway->id,
            'preferences' => $preferences->toArray(),
        ]);

        return $preferences;
    }

    /**
     * Create default preferences for a new user-gateway combination.
     */
    public function createDefaultPreferences(User $user, Gateway $gateway): RTUTrendPreference
    {
        $preferences = RTUTrendPreference::create([
            'user_id' => $user->id,
            'gateway_id' => $gateway->id,
            'selected_metrics' => RTUTrendPreference::getDefaultMetrics(),
            'time_range' => '24h',
            'chart_type' => 'line',
        ]);

        Log::info('Default RTU trend preferences created', [
            'user_id' => $user->id,
            'gateway_id' => $gateway->id,
        ]);

        return $preferences;
    }

    /**
     * Get dashboard configuration for a user.
     */
    public function getDashboardConfig(User $user, string $dashboardType = 'rtu'): array
    {
        return [
            'dashboard_type' => $dashboardType,
            'user_id' => $user->id,
            'available_metrics' => RTUTrendPreference::getAvailableMetrics(),
            'available_time_ranges' => RTUTrendPreference::getAvailableTimeRanges(),
            'available_chart_types' => RTUTrendPreference::getAvailableChartTypes(),
            'default_metrics' => RTUTrendPreference::getDefaultMetrics(),
        ];
    }

    /**
     * Reset user preferences to defaults for a specific gateway.
     */
    public function resetToDefaults(User $user, Gateway $gateway): RTUTrendPreference
    {
        $preferences = RTUTrendPreference::where('user_id', $user->id)
            ->where('gateway_id', $gateway->id)
            ->first();

        if ($preferences) {
            $preferences->update([
                'selected_metrics' => RTUTrendPreference::getDefaultMetrics(),
                'time_range' => '24h',
                'chart_type' => 'line',
            ]);
        } else {
            $preferences = $this->createDefaultPreferences($user, $gateway);
        }

        Log::info('RTU trend preferences reset to defaults', [
            'user_id' => $user->id,
            'gateway_id' => $gateway->id,
        ]);

        return $preferences;
    }

    /**
     * Delete user preferences for a specific gateway.
     */
    public function deletePreferences(User $user, Gateway $gateway): bool
    {
        $deleted = RTUTrendPreference::where('user_id', $user->id)
            ->where('gateway_id', $gateway->id)
            ->delete();

        if ($deleted) {
            Log::info('RTU trend preferences deleted', [
                'user_id' => $user->id,
                'gateway_id' => $gateway->id,
            ]);
        }

        return $deleted > 0;
    }

    /**
     * Get preferences for multiple gateways.
     */
    public function getBulkPreferences(User $user, array $gatewayIds): array
    {
        $preferences = RTUTrendPreference::where('user_id', $user->id)
            ->whereIn('gateway_id', $gatewayIds)
            ->get()
            ->keyBy('gateway_id');

        $result = [];
        foreach ($gatewayIds as $gatewayId) {
            if (isset($preferences[$gatewayId])) {
                $result[$gatewayId] = $preferences[$gatewayId];
            } else {
                // Create default preferences for missing gateways
                $gateway = Gateway::find($gatewayId);
                if ($gateway) {
                    $result[$gatewayId] = $this->createDefaultPreferences($user, $gateway);
                }
            }
        }

        return $result;
    }

    /**
     * Validate preference data before saving.
     */
    protected function validatePreferenceData(array $data): void
    {
        $errors = [];

        // Validate selected metrics
        if (isset($data['selected_metrics'])) {
            $availableMetrics = array_keys(RTUTrendPreference::getAvailableMetrics());
            $selectedMetrics = $data['selected_metrics'];

            if (!is_array($selectedMetrics) || empty($selectedMetrics)) {
                $errors['selected_metrics'] = 'At least one metric must be selected.';
            } else {
                foreach ($selectedMetrics as $metric) {
                    if (!in_array($metric, $availableMetrics)) {
                        $errors['selected_metrics'] = "Invalid metric: {$metric}";
                        break;
                    }
                }
            }
        }

        // Validate time range
        if (isset($data['time_range'])) {
            $availableRanges = array_keys(RTUTrendPreference::getAvailableTimeRanges());
            if (!in_array($data['time_range'], $availableRanges)) {
                $errors['time_range'] = "Invalid time range: {$data['time_range']}";
            }
        }

        // Validate chart type
        if (isset($data['chart_type'])) {
            $availableTypes = array_keys(RTUTrendPreference::getAvailableChartTypes());
            if (!in_array($data['chart_type'], $availableTypes)) {
                $errors['chart_type'] = "Invalid chart type: {$data['chart_type']}";
            }
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Export user preferences for backup or migration.
     */
    public function exportUserPreferences(User $user): array
    {
        $preferences = RTUTrendPreference::where('user_id', $user->id)
            ->with('gateway:id,name')
            ->get();

        return $preferences->map(function ($preference) {
            return [
                'gateway_id' => $preference->gateway_id,
                'gateway_name' => $preference->gateway->name ?? 'Unknown',
                'selected_metrics' => $preference->selected_metrics,
                'time_range' => $preference->time_range,
                'chart_type' => $preference->chart_type,
                'created_at' => $preference->created_at,
                'updated_at' => $preference->updated_at,
            ];
        })->toArray();
    }

    /**
     * Import user preferences from backup data.
     */
    public function importUserPreferences(User $user, array $preferencesData): array
    {
        $imported = [];
        $errors = [];

        foreach ($preferencesData as $data) {
            try {
                $gateway = Gateway::find($data['gateway_id']);
                if (!$gateway) {
                    $errors[] = "Gateway not found: {$data['gateway_id']}";
                    continue;
                }

                $preference = $this->updateTrendPreferences($user, $gateway, [
                    'selected_metrics' => $data['selected_metrics'],
                    'time_range' => $data['time_range'],
                    'chart_type' => $data['chart_type'],
                ]);

                $imported[] = $preference;
            } catch (\Exception $e) {
                $errors[] = "Failed to import preference for gateway {$data['gateway_id']}: {$e->getMessage()}";
            }
        }

        return [
            'imported' => $imported,
            'errors' => $errors,
        ];
    }
}