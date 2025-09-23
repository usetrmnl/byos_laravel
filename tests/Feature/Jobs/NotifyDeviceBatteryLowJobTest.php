<?php

declare(strict_types=1);

use App\Jobs\NotifyDeviceBatteryLowJob;
use App\Models\Device;
use App\Models\User;
use App\Notifications\BatteryLow;
use Illuminate\Support\Facades\Notification;

test('it sends battery low notification when battery is below threshold', function () {
    Notification::fake();

    config(['app.notifications.battery_low.warn_at_percent' => 20]);

    $user = User::factory()->create();
    $device = Device::factory()->create([
        'user_id' => $user->id,
        'last_battery_voltage' => 3.0, // This should result in low battery percentage
        'battery_notification_sent' => false,
    ]);

    $job = new NotifyDeviceBatteryLowJob();
    $job->handle();

    Notification::assertSentTo($user, BatteryLow::class);

    $device->refresh();
    expect($device->battery_notification_sent)->toBeTrue();
});

test('it does not send notification when battery is above threshold', function () {
    Notification::fake();

    config(['app.notifications.battery_low.warn_at_percent' => 20]);

    $user = User::factory()->create();
    $device = Device::factory()->create([
        'user_id' => $user->id,
        'last_battery_voltage' => 4.0, // This should result in high battery percentage
        'battery_notification_sent' => false,
    ]);

    $job = new NotifyDeviceBatteryLowJob();
    $job->handle();

    Notification::assertNotSentTo($user, BatteryLow::class);

    $device->refresh();
    expect($device->battery_notification_sent)->toBeFalse();
});

test('it does not send notification when already sent', function () {
    Notification::fake();

    config(['app.notifications.battery_low.warn_at_percent' => 20]);

    $user = User::factory()->create();
    $device = Device::factory()->create([
        'user_id' => $user->id,
        'last_battery_voltage' => 3.0, // Low battery
        'battery_notification_sent' => true, // Already sent
    ]);

    $job = new NotifyDeviceBatteryLowJob();
    $job->handle();

    Notification::assertNotSentTo($user, BatteryLow::class);
});

test('it resets notification flag when battery is above threshold', function () {
    Notification::fake();

    config(['app.notifications.battery_low.warn_at_percent' => 20]);

    $user = User::factory()->create();
    $device = Device::factory()->create([
        'user_id' => $user->id,
        'last_battery_voltage' => 4.0, // High battery
        'battery_notification_sent' => true, // Was previously sent
    ]);

    $job = new NotifyDeviceBatteryLowJob();
    $job->handle();

    Notification::assertNotSentTo($user, BatteryLow::class);

    $device->refresh();
    expect($device->battery_notification_sent)->toBeFalse();
});

test('it skips devices without associated user', function () {
    Notification::fake();

    config(['app.notifications.battery_low.warn_at_percent' => 20]);

    $device = Device::factory()->create([
        'user_id' => null,
        'last_battery_voltage' => 3.0, // Low battery
        'battery_notification_sent' => false,
    ]);

    $job = new NotifyDeviceBatteryLowJob();
    $job->handle();

    Notification::assertNothingSent();
});

test('it processes multiple devices correctly', function () {
    Notification::fake();

    config(['app.notifications.battery_low.warn_at_percent' => 20]);

    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $device1 = Device::factory()->create([
        'user_id' => $user1->id,
        'last_battery_voltage' => 3.0, // Low battery
        'battery_notification_sent' => false,
    ]);

    $device2 = Device::factory()->create([
        'user_id' => $user2->id,
        'last_battery_voltage' => 4.0, // High battery
        'battery_notification_sent' => false,
    ]);

    $job = new NotifyDeviceBatteryLowJob();
    $job->handle();

    Notification::assertSentTo($user1, BatteryLow::class);
    Notification::assertNotSentTo($user2, BatteryLow::class);

    $device1->refresh();
    $device2->refresh();

    expect($device1->battery_notification_sent)->toBeTrue();
    expect($device2->battery_notification_sent)->toBeFalse();
});
