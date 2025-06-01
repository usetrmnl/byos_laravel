<?php

use App\Models\Device;
use App\Models\DeviceLog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('device log belongs to a device', function () {
    $device = Device::factory()->create();
    $log = DeviceLog::factory()->create(['device_id' => $device->id]);

    expect($log->device)->toBeInstanceOf(Device::class)
        ->and($log->device->id)->toBe($device->id);
});

test('device log casts log_entry to array', function () {
    Device::factory()->create();
    $log = DeviceLog::factory()->create([
        'log_entry' => [
            'message' => 'test message',
            'level' => 'info',
            'timestamp' => time()
        ]
    ]);

    expect($log->log_entry)->toBeArray()
        ->and($log->log_entry['message'])->toBe('test message')
        ->and($log->log_entry['level'])->toBe('info');
});

test('device log casts device_timestamp to datetime', function () {
    Device::factory()->create();
    $timestamp = now();
    $log = DeviceLog::factory()->create([
        'device_timestamp' => $timestamp
    ]);

    expect($log->device_timestamp)->toBeInstanceOf(\Carbon\Carbon::class)
        ->and($log->device_timestamp->timestamp)->toBe($timestamp->timestamp);
});

test('device log factory creates valid data', function () {
    Device::factory()->create();
    $log = DeviceLog::factory()->create();

    expect($log->device_id)->toBeInt()
        ->and($log->device_timestamp)->toBeInstanceOf(\Carbon\Carbon::class)
        ->and($log->log_entry)->toBeArray()
        ->and($log->log_entry)->toHaveKeys(['creation_timestamp', 'device_status_stamp', 'log_id', 'log_message', 'log_codeline', 'log_sourcefile', 'additional_info']);
});

test('device log can be created with minimal required fields', function () {
    $device = Device::factory()->create();
    $log = DeviceLog::create([
        'device_id' => $device->id,
        'device_timestamp' => now(),
        'log_entry' => [
            'message' => 'test message'
        ]
    ]);

    expect($log->exists)->toBeTrue()
        ->and($log->device_id)->toBe($device->id)
        ->and($log->log_entry['message'])->toBe('test message');
});
