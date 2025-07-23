<?php

namespace App\Listeners;

use App\Events\UserPermissionsChanged;
use App\Services\PermissionCacheService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class InvalidateUserPermissionCache implements ShouldQueue
{
    use InteractsWithQueue;

    protected PermissionCacheService $cacheService;

    /**
     * Create the event listener.
     */
    public function __construct(PermissionCacheService $cacheService)
    {
        $this->cacheService = $cacheService;
    }

    /**
     * Handle the event.
     */
    public function handle(UserPermissionsChanged $event): void
    {
        try {
            // Invalidate cache for the affected user
            $this->cacheService->invalidateUserPermissions($event->user);

            // If gateway assignments changed, invalidate cache for all users with those gateways
            if (isset($event->changes['gateways'])) {
                $this->invalidateRelatedUsers($event->changes['gateways']);
            }

            // If device assignments changed, invalidate cache for users with related gateways
            if (isset($event->changes['devices'])) {
                $this->invalidateUsersWithRelatedDevices($event->changes['devices']);
            }

            Log::info('Permission cache invalidated after permission change', [
                'user_id' => $event->user->id,
                'change_type' => $event->changeType,
                'changed_by' => $event->changedBy->id,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to invalidate permission cache', [
                'user_id' => $event->user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw to ensure the job fails and can be retried
            throw $e;
        }
    }

    /**
     * Invalidate cache for users who might be affected by gateway changes
     */
    protected function invalidateRelatedUsers(array $gatewayChanges): void
    {
        $affectedGateways = array_merge(
            $gatewayChanges['added'] ?? [],
            $gatewayChanges['removed'] ?? []
        );

        if (empty($affectedGateways)) {
            return;
        }

        // Find users who have assignments to these gateways
        $affectedUserIds = \App\Models\UserGatewayAssignment::whereIn('gateway_id', $affectedGateways)
            ->distinct('user_id')
            ->pluck('user_id')
            ->toArray();

        $this->cacheService->invalidateMultipleUsers($affectedUserIds);

        Log::info('Invalidated cache for users affected by gateway changes', [
            'affected_gateways' => $affectedGateways,
            'affected_users' => count($affectedUserIds),
        ]);
    }

    /**
     * Invalidate cache for users who might be affected by device changes
     */
    protected function invalidateUsersWithRelatedDevices(array $deviceChanges): void
    {
        $affectedDevices = array_merge(
            $deviceChanges['added'] ?? [],
            $deviceChanges['removed'] ?? []
        );

        if (empty($affectedDevices)) {
            return;
        }

        // Find gateways that contain these devices
        $affectedGateways = \App\Models\Device::whereIn('id', $affectedDevices)
            ->distinct('gateway_id')
            ->pluck('gateway_id')
            ->toArray();

        // Find users who have assignments to these gateways or devices
        $gatewayUserIds = \App\Models\UserGatewayAssignment::whereIn('gateway_id', $affectedGateways)
            ->distinct('user_id')
            ->pluck('user_id')
            ->toArray();

        $deviceUserIds = \App\Models\UserDeviceAssignment::whereIn('device_id', $affectedDevices)
            ->distinct('user_id')
            ->pluck('user_id')
            ->toArray();

        $allAffectedUserIds = array_unique(array_merge($gatewayUserIds, $deviceUserIds));

        $this->cacheService->invalidateMultipleUsers($allAffectedUserIds);

        Log::info('Invalidated cache for users affected by device changes', [
            'affected_devices' => $affectedDevices,
            'affected_gateways' => $affectedGateways,
            'affected_users' => count($allAffectedUserIds),
        ]);
    }
}