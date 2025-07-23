<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'phone',
        'email_notifications',
        'sms_notifications',
        'notification_critical_only',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'email_notifications' => 'boolean',
        'sms_notifications' => 'boolean',
        'notification_critical_only' => 'boolean',
    ];

    /**
     * Route notifications for the Vonage channel.
     */
    public function routeNotificationForVonage($notification)
    {
        return $this->phone;
    }

    /**
     * Check if user should receive notifications for given severity
     */
    public function shouldReceiveNotification(string $severity): bool
    {
        if ($this->notification_critical_only) {
            return $severity === 'critical';
        }
        
        return true;
    }

    /**
     * Get user's preferred notification channels
     */
    public function getNotificationChannels(): array
    {
        $channels = [];
        
        if ($this->email_notifications) {
            $channels[] = 'mail';
        }
        
        if ($this->sms_notifications && $this->phone) {
            $channels[] = 'vonage';
        }
        
        return $channels;
    }

    /**
     * Check if user is an admin
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Check if user is an operator
     */
    public function isOperator(): bool
    {
        return $this->role === 'operator';
    }

    /**
     * Get devices assigned to this user
     */
    public function getAssignedDevices()
    {
        return $this->belongsToMany(Device::class, 'user_device_assignments')
                    ->withPivot(['assigned_at', 'assigned_by'])
                    ->withTimestamps();
    }

    /**
     * Get device assignments for this user
     */
    public function deviceAssignments()
    {
        return $this->hasMany(UserDeviceAssignment::class);
    }

    /**
     * Get gateway assignments for this user
     */
    public function gatewayAssignments()
    {
        return $this->hasMany(UserGatewayAssignment::class);
    }

    /**
     * Get assigned device IDs for this user
     */
    public function getAssignedDeviceIds(): array
    {
        if ($this->isAdmin()) {
            return Device::pluck('id')->toArray();
        }

        return $this->deviceAssignments()->pluck('device_id')->toArray();
    }

    /**
     * Get assigned gateway IDs for this user
     */
    public function getAssignedGatewayIds(): array
    {
        if ($this->isAdmin()) {
            return Gateway::pluck('id')->toArray();
        }

        return $this->gatewayAssignments()->pluck('gateway_id')->toArray();
    }

    /**
     * Check if user can access a specific device
     */
    public function canAccessDevice(int $deviceId): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return in_array($deviceId, $this->getAssignedDeviceIds());
    }

    /**
     * Check if user can access a specific gateway
     */
    public function canAccessGateway(int $gatewayId): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return in_array($gatewayId, $this->getAssignedGatewayIds());
    }

    /**
     * Get dashboard configurations for this user
     */
    public function dashboardConfigs()
    {
        return $this->hasMany(UserDashboardConfig::class);
    }

    /**
     * Get dashboard access logs for this user
     */
    public function accessLogs()
    {
        return $this->hasMany(DashboardAccessLog::class);
    }

    /**
     * Get alerts for devices assigned to this user
     */
    public function getAssignedAlerts()
    {
        if ($this->isAdmin()) {
            return Alert::query();
        }

        $assignedDeviceIds = $this->getAssignedDevices()->pluck('devices.id');
        return Alert::whereIn('device_id', $assignedDeviceIds);
    }

    /**
     * Check if user should receive notification for given alert
     */
    public function shouldReceiveAlert(Alert $alert): bool
    {
        // Operators don't receive email notifications
        if ($this->isOperator()) {
            return false;
        }

        // Check if user has email notifications enabled
        if (!$this->email_notifications) {
            // Critical alerts bypass the email_notifications preference
            return $alert->severity === 'critical';
        }

        // Check critical-only preference
        if ($this->notification_critical_only) {
            return $alert->severity === 'critical';
        }

        return true;
    }
}
