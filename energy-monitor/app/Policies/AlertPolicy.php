<?php

namespace App\Policies;

use App\Models\Alert;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class AlertPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true; // Both admin and operator can view alerts
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Alert $alert): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        // Operators can only view alerts for their assigned devices
        return $user->getAssignedDevices()->where('devices.id', $alert->device_id)->exists();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false; // Alerts are created automatically by the system
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Alert $alert): bool
    {
        // Both admin and operator can resolve alerts they can view
        return $this->view($user, $alert);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Alert $alert): bool
    {
        return $user->isAdmin(); // Only admins can delete alerts
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Alert $alert): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Alert $alert): bool
    {
        return $user->isAdmin();
    }
}
