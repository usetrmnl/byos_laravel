<?php

use App\Models\Device;
use Illuminate\Support\Facades\Storage;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    Storage::disk('public')->makeDirectory('/images/generated');
});

test('device with firmware version 1.5.1 gets bmp format', function () {
    $device = Device::factory()->create([
        'mac_address' => '00:11:22:33:44:55',
        'api_key' => 'test-api-key',
        'current_screen_image' => 'test-image',
        'last_firmware_version' => '1.5.1',
    ]);

    // Create both bmp and png files
    Storage::disk('public')->put('images/generated/test-image.bmp', 'fake bmp content');
    Storage::disk('public')->put('images/generated/test-image.png', 'fake png content');

    // Test /api/display endpoint
    $displayResponse = $this->withHeaders([
        'id' => $device->mac_address,
        'access-token' => $device->api_key,
        'rssi' => -70,
        'battery_voltage' => 3.8,
        'fw-version' => '1.5.1',
    ])->get('/api/display');

    $displayResponse->assertOk()
        ->assertJson([
            'filename' => 'test-image.bmp',
        ]);

    // Test /api/current_screen endpoint
    $currentScreenResponse = $this->withHeaders([
        'access-token' => $device->api_key,
    ])->get('/api/current_screen');

    $currentScreenResponse->assertOk()
        ->assertJson([
            'filename' => 'test-image.bmp',
        ]);
});

test('device with firmware version 1.5.2 gets png format', function () {
    $device = Device::factory()->create([
        'mac_address' => '00:11:22:33:44:55',
        'api_key' => 'test-api-key',
        'current_screen_image' => 'test-image',
        'last_firmware_version' => '1.5.2',
    ]);

    // Create both bmp and png files
    Storage::disk('public')->put('images/generated/test-image.png', 'fake bmp content');

    // Test /api/display endpoint
    $displayResponse = $this->withHeaders([
        'id' => $device->mac_address,
        'access-token' => $device->api_key,
        'rssi' => -70,
        'battery_voltage' => 3.8,
        'fw-version' => '1.5.2',
    ])->get('/api/display');

    $displayResponse->assertOk()
        ->assertJson([
            'filename' => 'test-image.png',
        ]);

    // Test /api/current_screen endpoint
    $currentScreenResponse = $this->withHeaders([
        'access-token' => $device->api_key,
    ])->get('/api/current_screen');

    $currentScreenResponse->assertOk()
        ->assertJson([
            'filename' => 'test-image.png',
        ]);
});

test('device falls back to bmp when png does not exist', function () {
    $device = Device::factory()->create([
        'mac_address' => '00:11:22:33:44:55',
        'api_key' => 'test-api-key',
        'current_screen_image' => 'test-image',
        'last_firmware_version' => '1.5.2',
    ]);

    // Create only bmp file
    Storage::disk('public')->put('images/generated/test-image.bmp', 'fake bmp content');

    // Test /api/display endpoint
    $displayResponse = $this->withHeaders([
        'id' => $device->mac_address,
        'access-token' => $device->api_key,
        'rssi' => -70,
        'battery_voltage' => 3.8,
        'fw-version' => '1.5.2',
    ])->get('/api/display');

    $displayResponse->assertOk()
        ->assertJson([
            'filename' => 'test-image.bmp',
        ]);

    // Test /api/current_screen endpoint
    $currentScreenResponse = $this->withHeaders([
        'access-token' => $device->api_key,
    ])->get('/api/current_screen');

    $currentScreenResponse->assertOk()
        ->assertJson([
            'filename' => 'test-image.bmp',
        ]);
});
