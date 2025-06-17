<?php

namespace App\Notifications;

use App\Models\Device;
use App\Notifications\Channels\WebhookChannel;
use App\Notifications\Messages\WebhookMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BatteryLow extends Notification
{
    use Queueable;

    private Device $device;

    /**
     * Create a new notification instance.
     */
    public function __construct(Device $device)
    {
        $this->device = $device;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', WebhookChannel::class];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->markdown('mail.battery-low', ['device' => $this->device]);
    }

    public function toWebhook(object $notifiable)
    {
        return WebhookMessage::create()
            ->data([
                'topic' => config('services.webhook.notifications.topic', 'battery.low'),
                'message' => "Battery below {$this->device->battery_percent}% on device: {$this->device->name}",
                'device_id' => $this->device->id,
                'device_name' => $this->device->name,
                'battery_percent' => $this->device->battery_percent,

            ])
            ->userAgent(config('app.name'))
            ->header('X-TrmnlByos-Event', 'battery.low');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'device_name' => $this->device->name,
            'battery_percent' => $this->device->battery_percent,
        ];
    }
}
