<?php

use App\Jobs\CleanupDeviceLogsJob;
use App\Models\Device;
use App\Models\DeviceLog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it keeps only the 50 most recent logs per device', function () {
    // Create two devices
    $device1 = Device::factory()->create();
    $device2 = Device::factory()->create();

    // Create 60 logs for each device with different timestamps
    for ($i = 0; $i < 60; $i++) {
        DeviceLog::factory()->create([
            'device_id' => $device1->id,
            'device_timestamp' => now()->subMinutes($i),
        ]);

        DeviceLog::factory()->create([
            'device_id' => $device2->id,
            'device_timestamp' => now()->subMinutes($i),
        ]);
    }

    // Run the cleanup job
    CleanupDeviceLogsJob::dispatchSync();

    // Assert each device has exactly 50 logs
    expect($device1->logs()->count())->toBe(50)
        ->and($device2->logs()->count())->toBe(50);

    // Assert the remaining logs are the most recent ones
    $device1Logs = $device1->logs()->orderByDesc('device_timestamp')->get();
    $device2Logs = $device2->logs()->orderByDesc('device_timestamp')->get();

    // Check that the timestamps are in descending order
    for ($i = 0; $i < 49; $i++) {
        expect($device1Logs[$i]->device_timestamp->gt($device1Logs[$i + 1]->device_timestamp))->toBeTrue()
            ->and($device2Logs[$i]->device_timestamp->gt($device2Logs[$i + 1]->device_timestamp))->toBeTrue();
    }
});
