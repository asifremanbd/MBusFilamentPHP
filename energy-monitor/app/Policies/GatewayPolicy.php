<?php

namespace App\Policies;

use App\Models\Gateway;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class GatewayPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true; // Both admin and operator can view gateways
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Gateway $gateway): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        // Operators can only view gateways that have assigned devices
        return $user->getAssignedDevices()
            ->whereHas('gateway', function ($query) use ($gateway) {
                $query->where('gateways.id', $gateway->id);
            })
            ->exists();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Gateway $gateway): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Gateway $gateway): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Gateway $gateway): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Gateway $gateway): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can control RTU gateway I/O operations.
     */
    public function control(User $user, Gateway $gateway): bool
    {
        // Only admins can control RTU I/O operations for security reasons
        // In the future, this could be expanded to include specific control permissions
        if ($user->isAdmin()) {
            return true;
        }

        // Operators with specific control permissions could be added here
        // For now, we restrict control operations to admins only
        return false;
    }
}
