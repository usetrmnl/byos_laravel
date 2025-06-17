<?php

namespace App\Jobs;

use App\Models\Device;
use App\Models\User;
use App\Notifications\BatteryLow;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NotifyDeviceBatteryLowJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct() {}

    public function handle(): void
    {
        $devices = Device::all();
        $batteryThreshold = config('app.notifications.battery_low.warn_at_percent');

        foreach ($devices as $device) {
            $batteryPercent = $device->battery_percent;

            // If battery is above threshold, reset the notification flag
            if ($batteryPercent > $batteryThreshold && $device->battery_notification_sent) {
                $device->battery_notification_sent = false;
                $device->save();

                continue;
            }

            // Skip if battery is not low or notification was already sent
            if ($batteryPercent > $batteryThreshold || $device->battery_notification_sent) {
                continue;
            }

            /** @var User|null $user */
            $user = $device->user;

            if (! $user) {
                continue; // Skip if no user is associated with the device
            }

            // Send notification and mark as sent
            $user->notify(new BatteryLow($device));
            $device->battery_notification_sent = true;
            $device->save();
        }
    }
}
