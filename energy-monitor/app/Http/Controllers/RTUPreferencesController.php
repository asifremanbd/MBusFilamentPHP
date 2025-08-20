<?php

namespace App\Http\Controllers;

use App\Models\Gateway;
use App\Models\RTUTrendPreference;
use App\Services\RTUDashboardConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class RTUPreferencesController extends Controller
{
    protected RTUDashboardConfigService $configService;

    public function __construct(RTUDashboardConfigService $configService)
    {
        $this->configService = $configService;
    }

    /**
     * Get trend preferences for a specific gateway.
     */
    public function getTrendPreferences(Request $request, Gateway $gateway): JsonResponse
    {
        $this->authorize('view', $gateway);

        $user = Auth::user();
        $preferences = $this->configService->getTrendPreferences($user, $gateway);
        $config = $this->configService->getDashboardConfig($user);

        return response()->json([
            'preferences' => $preferences,
            'config' => $config,
        ]);
    }

    /**
     * Update trend preferences for a specific gateway.
     */
    public function updateTrendPreferences(Request $request, Gateway $gateway): JsonResponse
    {
        $this->authorize('view', $gateway);

        $validated = $request->validate([
            'selected_metrics' => 'required|array|min:1',
            'selected_metrics.*' => 'string|in:' . implode(',', array_keys(RTUTrendPreference::getAvailableMetrics())),
            'time_range' => 'required|string|in:' . implode(',', array_keys(RTUTrendPreference::getAvailableTimeRanges())),
            'chart_type' => 'required|string|in:' . implode(',', array_keys(RTUTrendPreference::getAvailableChartTypes())),
        ]);

        try {
            $user = Auth::user();
            $preferences = $this->configService->updateTrendPreferences($user, $gateway, $validated);

            return response()->json([
                'success' => true,
                'message' => 'Preferences updated successfully',
                'preferences' => $preferences,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update preferences: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reset preferences to defaults for a specific gateway.
     */
    public function resetToDefaults(Request $request, Gateway $gateway): JsonResponse
    {
        $this->authorize('view', $gateway);

        try {
            $user = Auth::user();
            $preferences = $this->configService->resetToDefaults($user, $gateway);

            return response()->json([
                'success' => true,
                'message' => 'Preferences reset to defaults',
                'preferences' => $preferences,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reset preferences: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get available configuration options.
     */
    public function getConfigOptions(): JsonResponse
    {
        $user = Auth::user();
        $config = $this->configService->getDashboardConfig($user);

        return response()->json($config);
    }

    /**
     * Get preferences for multiple gateways.
     */
    public function getBulkPreferences(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'gateway_ids' => 'required|array|min:1',
            'gateway_ids.*' => 'integer|exists:gateways,id',
        ]);

        $user = Auth::user();
        
        // Check authorization for all gateways
        $gateways = Gateway::whereIn('id', $validated['gateway_ids'])->get();
        foreach ($gateways as $gateway) {
            $this->authorize('view', $gateway);
        }

        $preferences = $this->configService->getBulkPreferences($user, $validated['gateway_ids']);

        return response()->json([
            'preferences' => $preferences,
        ]);
    }

    /**
     * Export user preferences.
     */
    public function exportPreferences(): JsonResponse
    {
        $user = Auth::user();
        $preferences = $this->configService->exportUserPreferences($user);

        return response()->json([
            'preferences' => $preferences,
            'exported_at' => now(),
            'user_id' => $user->id,
        ]);
    }

    /**
     * Import user preferences.
     */
    public function importPreferences(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'preferences' => 'required|array',
            'preferences.*.gateway_id' => 'required|integer|exists:gateways,id',
            'preferences.*.selected_metrics' => 'required|array|min:1',
            'preferences.*.time_range' => 'required|string',
            'preferences.*.chart_type' => 'required|string',
        ]);

        $user = Auth::user();
        
        // Check authorization for all gateways in the import
        $gatewayIds = collect($validated['preferences'])->pluck('gateway_id')->unique();
        $gateways = Gateway::whereIn('id', $gatewayIds)->get();
        foreach ($gateways as $gateway) {
            $this->authorize('view', $gateway);
        }

        try {
            $result = $this->configService->importUserPreferences($user, $validated['preferences']);

            return response()->json([
                'success' => true,
                'message' => 'Preferences imported successfully',
                'imported_count' => count($result['imported']),
                'errors' => $result['errors'],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to import preferences: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete preferences for a specific gateway.
     */
    public function deletePreferences(Request $request, Gateway $gateway): JsonResponse
    {
        $this->authorize('view', $gateway);

        try {
            $user = Auth::user();
            $deleted = $this->configService->deletePreferences($user, $gateway);

            if ($deleted) {
                return response()->json([
                    'success' => true,
                    'message' => 'Preferences deleted successfully',
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No preferences found to delete',
                ], 404);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete preferences: ' . $e->getMessage(),
            ], 500);
        }
    }
}