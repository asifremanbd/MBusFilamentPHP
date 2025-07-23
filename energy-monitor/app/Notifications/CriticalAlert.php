<?php

namespace App\Notifications;

use App\Models\Alert;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\VonageMessage;
use Illuminate\Notifications\Notification;

class CriticalAlert extends Notification implements ShouldQueue
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
        // Always send critical alerts regardless of user preference
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
            ->subject('🚨 CRITICAL ALERT: Energy Monitor System')
            ->line('⚠️ **CRITICAL ALERT** ⚠️')
            ->line('A critical condition has been detected on your energy monitoring system that requires immediate attention.')
            ->line('**Alert Details:**')
            ->line("Gateway: {$gateway->name}")
            ->line("Device: {$device->name}")
            ->line("Parameter: {$this->alert->parameter_name}")
            ->line("Value: {$this->alert->value}")
            ->line("Severity: CRITICAL")
            ->line("Time: {$this->alert->timestamp->format('Y-m-d H:i:s')}")
            ->line('**Message:**')
            ->line($this->alert->message)
            ->action('🔥 View Critical Alert', url("/admin/alerts/{$this->alert->id}/edit"))
            ->line('⚠️ **IMMEDIATE ACTION REQUIRED** ⚠️')
            ->line('Please investigate and resolve this issue immediately to prevent potential system damage or service disruption.');
    }

    /**
     * Get the Vonage / SMS representation of the notification.
     */
    public function toVonage(object $notifiable): VonageMessage
    {
        $device = $this->alert->device;
        $message = "🚨 CRITICAL ALERT: {$device->name} - {$this->alert->parameter_name} = {$this->alert->value} - IMMEDIATE ACTION REQUIRED";
        
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
            'is_critical' => true,
        ];
    }
}
