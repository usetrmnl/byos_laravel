<?php

declare(strict_types=1);

use App\Models\Device;
use App\Models\User;
use App\Notifications\BatteryLow;
use App\Notifications\Channels\WebhookChannel;
use Illuminate\Notifications\Messages\MailMessage;

test('battery low notification has correct via channels', function () {
    $device = Device::factory()->create();
    $notification = new BatteryLow($device);

    expect($notification->via(new User()))->toBe(['mail', WebhookChannel::class]);
});

test('battery low notification creates correct mail message', function () {
    $device = Device::factory()->create([
        'name' => 'Test Device',
        'last_battery_voltage' => 3.0,
    ]);

    $notification = new BatteryLow($device);
    $mailMessage = $notification->toMail(new User());

    expect($mailMessage)->toBeInstanceOf(MailMessage::class);
    expect($mailMessage->markdown)->toBe('mail.battery-low');
    expect($mailMessage->viewData['device'])->toBe($device);
});

test('battery low notification creates correct webhook message', function () {
    config([
        'services.webhook.notifications.topic' => 'battery.low',
        'app.name' => 'Test App',
    ]);

    $device = Device::factory()->create([
        'name' => 'Test Device',
        'last_battery_voltage' => 3.0,
    ]);

    $notification = new BatteryLow($device);
    $webhookMessage = $notification->toWebhook(new User());

    expect($webhookMessage->toArray())->toBe([
        'query' => null,
        'data' => [
            'topic' => 'battery.low',
            'message' => "Battery below {$device->battery_percent}% on device: Test Device",
            'device_id' => $device->id,
            'device_name' => 'Test Device',
            'battery_percent' => $device->battery_percent,
        ],
        'headers' => [
            'User-Agent' => 'Test App',
            'X-TrmnlByos-Event' => 'battery.low',
        ],
        'verify' => false,
    ]);
});

test('battery low notification creates correct array representation', function () {
    $device = Device::factory()->create([
        'name' => 'Test Device',
        'last_battery_voltage' => 3.0,
    ]);

    $notification = new BatteryLow($device);
    $array = $notification->toArray(new User());

    expect($array)->toBe([
        'device_name' => 'Test Device',
        'battery_percent' => $device->battery_percent,
    ]);
});
