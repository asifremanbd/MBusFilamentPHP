<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\Register;
use App\Models\User;
use App\Notifications\OutOfRangeAlert;
use App\Notifications\OffHoursAlert;
use App\Notifications\CriticalAlert;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class AlertService
{
    /**
     * Process alerts for a reading value
     */
    public function processAlerts(Register $register, float $value, string $timestamp): array
    {
        $alerts = [];
        $readingTime = Carbon::parse($timestamp);

        // Check for out of range alerts
        if ($register->normal_range && $this->isOutOfRange($value, $register->normal_range)) {
            $alert = $this->createOutOfRangeAlert($register, $value, $readingTime);
            $alerts[] = $alert;
        }

        // Check for off-hours alerts
        if ($this->isOffHours($readingTime)) {
            $alert = $this->createOffHoursAlert($register, $value, $readingTime);
            $alerts[] = $alert;
        }

        // Check for critical thresholds
        if ($register->critical && $this->isCriticalValue($register, $value)) {
            $alert = $this->createCriticalAlert($register, $value, $readingTime);
            $alerts[] = $alert;
        }

        return $alerts;
    }

    /**
     * Create out of range alert
     */
    private function createOutOfRangeAlert(Register $register, float $value, Carbon $timestamp): Alert
    {
        $severity = $register->critical ? 'critical' : 'warning';
        $message = "Value {$value} {$register->unit} is outside normal range {$register->normal_range}";

        $alert = Alert::create([
            'device_id' => $register->device_id,
            'parameter_name' => $register->parameter_name,
            'value' => $value,
            'severity' => $severity,
            'timestamp' => $timestamp,
            'resolved' => false,
            'message' => $message
        ]);

        // Send notifications
        $this->sendNotifications($alert, OutOfRangeAlert::class);

        Log::warning("Out of range alert created", [
            'device_id' => $register->device_id,
            'parameter' => $register->parameter_name,
            'value' => $value,
            'normal_range' => $register->normal_range,
            'severity' => $severity
        ]);

        return $alert;
    }

    /**
     * Create off-hours alert
     */
    private function createOffHoursAlert(Register $register, float $value, Carbon $timestamp): Alert
    {
        $message = "Reading received during off-hours (10 PM - 6 AM)";

        $alert = Alert::create([
            'device_id' => $register->device_id,
            'parameter_name' => $register->parameter_name,
            'value' => $value,
            'severity' => 'info',
            'timestamp' => $timestamp,
            'resolved' => false,
            'message' => $message
        ]);

        // Send notifications
        $this->sendNotifications($alert, OffHoursAlert::class);

        Log::info("Off-hours alert created", [
            'device_id' => $register->device_id,
            'parameter' => $register->parameter_name,
            'timestamp' => $timestamp->format('Y-m-d H:i:s')
        ]);

        return $alert;
    }

    /**
     * Create critical alert
     */
    private function createCriticalAlert(Register $register, float $value, Carbon $timestamp): Alert
    {
        $message = "CRITICAL: {$register->parameter_name} reached critical value {$value} {$register->unit}";

        $alert = Alert::create([
            'device_id' => $register->device_id,
            'parameter_name' => $register->parameter_name,
            'value' => $value,
            'severity' => 'critical',
            'timestamp' => $timestamp,
            'resolved' => false,
            'message' => $message
        ]);

        // Send notifications
        $this->sendNotifications($alert, CriticalAlert::class);

        Log::critical("Critical alert created", [
            'device_id' => $register->device_id,
            'parameter' => $register->parameter_name,
            'value' => $value,
            'severity' => 'critical'
        ]);

        return $alert;
    }

    /**
     * Send notifications to users
     */
    private function sendNotifications(Alert $alert, string $notificationClass): void
    {
        // Get all admin users (operators don't receive email notifications)
        $users = User::where('role', 'admin')->get();

        foreach ($users as $user) {
            if ($user->shouldReceiveAlert($alert)) {
                try {
                    $notification = new $notificationClass($alert);
                    $user->notify($notification);
                    
                    Log::info("Alert notification sent", [
                        'user_id' => $user->id,
                        'alert_id' => $alert->id,
                        'notification_type' => $notificationClass,
                        'alert_severity' => $alert->severity,
                        'delivery_status' => 'success'
                    ]);
                } catch (\Exception $e) {
                    Log::error("Failed to send notification", [
                        'user_id' => $user->id,
                        'alert_id' => $alert->id,
                        'notification_type' => $notificationClass,
                        'error' => $e->getMessage()
                    ]);
                }
            } else {
                Log::debug("Notification skipped due to user preferences", [
                    'user_id' => $user->id,
                    'alert_id' => $alert->id,
                    'alert_severity' => $alert->severity,
                    'user_email_notifications' => $user->email_notifications,
                    'user_critical_only' => $user->notification_critical_only
                ]);
            }
        }
    }

    /**
     * Check if value is outside the normal range
     */
    private function isOutOfRange(float $value, string $normalRange): bool
    {
        // Parse normal range format like "220-400" or "220–400"
        $normalRange = str_replace(['–', '—'], '-', $normalRange);
        
        if (strpos($normalRange, '-') !== false) {
            $parts = explode('-', $normalRange);
            if (count($parts) === 2) {
                $min = (float) trim($parts[0]);
                $max = (float) trim($parts[1]);
                return $value < $min || $value > $max;
            }
        }
        
        return false;
    }

    /**
     * Check if the time is during off-hours (10 PM to 6 AM)
     */
    private function isOffHours(Carbon $time): bool
    {
        $hour = $time->hour;
        return $hour >= 22 || $hour < 6;
    }

    /**
     * Check if value is critical (beyond 20% of normal range)
     */
    private function isCriticalValue(Register $register, float $value): bool
    {
        if (!$register->normal_range) {
            return false;
        }

        $normalRange = str_replace(['–', '—'], '-', $register->normal_range);
        
        if (strpos($normalRange, '-') !== false) {
            $parts = explode('-', $normalRange);
            if (count($parts) === 2) {
                $min = (float) trim($parts[0]);
                $max = (float) trim($parts[1]);
                $range = $max - $min;
                $criticalBuffer = $range * 0.2; // 20% beyond normal range
                
                return $value < ($min - $criticalBuffer) || $value > ($max + $criticalBuffer);
            }
        }
        
        return false;
    }

    /**
     * Resolve an alert
     */
    public function resolveAlert(int $alertId, int $userId): bool
    {
        $alert = Alert::find($alertId);
        
        if (!$alert) {
            return false;
        }

        $alert->update([
            'resolved' => true,
            'resolved_by' => $userId,
            'resolved_at' => now()
        ]);

        Log::info("Alert resolved", [
            'alert_id' => $alertId,
            'resolved_by' => $userId
        ]);

        return true;
    }

    /**
     * Get active alerts for a device
     */
    public function getActiveAlerts(int $deviceId): \Illuminate\Database\Eloquent\Collection
    {
        return Alert::where('device_id', $deviceId)
            ->where('resolved', false)
            ->orderBy('severity', 'desc')
            ->orderBy('timestamp', 'desc')
            ->get();
    }

    /**
     * Get alert statistics
     */
    public function getAlertStats(): array
    {
        $totalAlerts = Alert::count();
        $activeAlerts = Alert::where('resolved', false)->count();
        $criticalAlerts = Alert::where('resolved', false)
            ->where('severity', 'critical')
            ->count();
        $todayAlerts = Alert::whereDate('timestamp', today())->count();

        return [
            'total' => $totalAlerts,
            'active' => $activeAlerts,
            'critical' => $criticalAlerts,
            'today' => $todayAlerts
        ];
    }
} 