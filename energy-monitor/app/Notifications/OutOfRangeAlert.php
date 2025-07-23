<?php

namespace App\Notifications;

use App\Models\Alert;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\VonageMessage;
use Illuminate\Notifications\Notification;

class OutOfRangeAlert extends Notification implements ShouldQueue
{
    use Queueable;

    protected Alert $alert;

    /**
     * Create a new notification instance.
     */
    public function __construct(Alert $alert)
    {
        $this->alert = $alert;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        // Only send if user wants this severity level
        if (!$notifiable->shouldReceiveNotification($this->alert->severity)) {
            return [];
        }

        return $notifiable->getNotificationChannels();
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $device = $this->alert->device;
        $gateway = $device->gateway;
        
        return (new MailMessage)
            ->subject('Energy Monitor Alert: Out of Range Value')
            ->line('An out of range value has been detected on your energy monitoring system.')
            ->line('**Alert Details:**')
            ->line("Gateway: {$gateway->name}")
            ->line("Device: {$device->name}")
            ->line("Parameter: {$this->alert->parameter_name}")
            ->line("Value: {$this->alert->value}")
            ->line("Severity: " . ucfirst($this->alert->severity))
            ->line("Time: {$this->alert->timestamp->format('Y-m-d H:i:s')}")
            ->line('**Message:**')
            ->line($this->alert->message)
            ->action('View Alert Details', url("/admin/alerts/{$this->alert->id}/edit"))
            ->line('Please check your system and take appropriate action if required.');
    }

    /**
     * Get the Vonage / SMS representation of the notification.
     */
    public function toVonage(object $notifiable): VonageMessage
    {
        $device = $this->alert->device;
        $message = "ENERGY ALERT: {$device->name} - {$this->alert->parameter_name} = {$this->alert->value} (Out of Range)";
        
        if ($this->alert->severity === 'critical') {
            $message = "CRITICAL " . $message;
        }
        
        return (new VonageMessage)
            ->content($message);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'alert_id' => $this->alert->id,
            'device_id' => $this->alert->device_id,
            'parameter_name' => $this->alert->parameter_name,
            'value' => $this->alert->value,
            'severity' => $this->alert->severity,
            'message' => $this->alert->message,
            'timestamp' => $this->alert->timestamp->toISOString(),
        ];
    }
}
