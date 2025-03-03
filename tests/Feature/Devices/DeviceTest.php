<?php

use App\Models\Device;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('device can be created with basic attributes', function () {
    $device = Device::factory()->create([
        'name' => 'Test Device',
    ]);

    expect($device)->toBeInstanceOf(Device::class)
        ->and($device->name)->toBe('Test Device');
});

test('battery percentage is calculated correctly', function () {
    $cases = [
        ['voltage' => 3.0, 'expected' => 0],    // Min voltage
        ['voltage' => 4.2, 'expected' => 100],  // Max voltage
        ['voltage' => 2.9, 'expected' => 0],    // Below min
        ['voltage' => 4.3, 'expected' => 100],  // Above max
        ['voltage' => 3.6, 'expected' => 50.0],   // Middle voltage
        ['voltage' => 3.3, 'expected' => 25.0],   // Quarter voltage
    ];

    foreach ($cases as $case) {
        $device = Device::factory()->create([
            'last_battery_voltage' => $case['voltage'],
        ]);

        expect($device->battery_percent)->toBe($case['expected'])
            ->and($device->last_battery_voltage)->toBe($case['voltage']);
    }
});

test('wifi strength is determined correctly', function () {
    $cases = [
        ['rssi' => 0, 'expected' => 0],     // No signal
        ['rssi' => -90, 'expected' => 1],   // Weak signal
        ['rssi' => -70, 'expected' => 2],   // Moderate signal
        ['rssi' => -50, 'expected' => 3],   // Strong signal
    ];

    foreach ($cases as $case) {
        $device = Device::factory()->create([
            'last_rssi_level' => $case['rssi'],
        ]);

        expect($device->wifi_strengh)->toBe($case['expected'])
            ->and($device->last_rssi_level)->toBe($case['rssi']);
    }
});

test('proxy cloud attribute is properly cast to boolean', function () {
    $device = Device::factory()->create([
        'proxy_cloud' => true,
    ]);

    expect($device->proxy_cloud)->toBeTrue();

    $device->update(['proxy_cloud' => false]);
    expect($device->proxy_cloud)->toBeFalse();
});

test('last log request is properly cast to json', function () {
    $logData = ['status' => 'success', 'timestamp' => '2024-03-04 12:00:00'];

    $device = Device::factory()->create([
        'last_log_request' => $logData,
    ]);

    expect($device->last_log_request)
        ->toBeArray()
        ->toHaveKey('status')
        ->toHaveKey('timestamp');
});
