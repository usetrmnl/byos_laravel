<?php

use App\Models\Device;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
});

test('device can fetch display data with valid credentials', function () {
    $device = Device::factory()->create([
        'mac_address' => '00:11:22:33:44:55',
        'api_key' => 'test-api-key',
        'current_screen_image' => 'test-image',
    ]);

    $response = $this->withHeaders([
        'id' => $device->mac_address,
        'access-token' => $device->api_key,
        'rssi' => -70,
        'battery_voltage' => 3.8,
        'fw-version' => '1.0.0',
    ])->get('/api/display');

    $response->assertOk()
        ->assertJson([
            'status' => '0',
            'filename' => 'test-image.bmp',
            'refresh_rate' => 900,
            'reset_firmware' => false,
            'update_firmware' => false,
            'firmware_url' => null,
            'special_function' => 'sleep',
        ]);

    expect($device->fresh())
        ->last_rssi_level->toBe(-70)
        ->last_battery_voltage->toBe(3.8)
        ->last_firmware_version->toBe('1.0.0');
});

test('display endpoint includes image_url_timeout when configured', function () {
    $device = Device::factory()->create([
        'mac_address' => '00:11:22:33:44:55',
        'api_key' => 'test-api-key',
    ]);

    config(['services.trmnl.image_url_timeout' => 300]);

    $response = $this->withHeaders([
        'id' => $device->mac_address,
        'access-token' => $device->api_key,
        'rssi' => -70,
        'battery_voltage' => 3.8,
        'fw-version' => '1.0.0',
    ])->get('/api/display');

    $response->assertOk()
        ->assertJson([
            'image_url_timeout' => 300,
        ]);
});

test('display endpoint omits image_url_timeout when not configured', function () {
    $device = Device::factory()->create([
        'mac_address' => '00:11:22:33:44:55',
        'api_key' => 'test-api-key',
    ]);

    config(['services.trmnl.image_url_timeout' => null]);

    $response = $this->withHeaders([
        'id' => $device->mac_address,
        'access-token' => $device->api_key,
        'rssi' => -70,
        'battery_voltage' => 3.8,
        'fw-version' => '1.0.0',
    ])->get('/api/display');

    $response->assertOk()
        ->assertJsonMissing(['image_url_timeout']);
});

test('new device is auto-assigned to user with auto-assign enabled', function () {
    $user = User::factory()->create(['assign_new_devices' => true]);

    $response = $this->withHeaders([
        'id' => '00:11:22:33:44:55',
        'access-token' => 'new-device-key',
        'rssi' => -70,
        'battery_voltage' => 3.8,
        'fw-version' => '1.0.0',
    ])->get('/api/display');

    $response->assertOk();

    $device = Device::where('mac_address', '00:11:22:33:44:55')->first();
    expect($device)
        ->not->toBeNull()
        ->user_id->toBe($user->id)
        ->api_key->toBe('new-device-key');
});

test('device setup endpoint returns correct data', function () {
    $device = Device::factory()->create([
        'mac_address' => '00:11:22:33:44:55',
        'api_key' => 'test-api-key',
        'friendly_id' => 'test-device',
    ]);

    $response = $this->withHeaders([
        'id' => $device->mac_address,
    ])->get('/api/setup');

    $response->assertOk()
        ->assertJson([
            'api_key' => 'test-api-key',
            'friendly_id' => 'test-device',
            'message' => 'Welcome to TRMNL BYOS',
        ]);
});

test('device can submit logs', function () {
    $device = Device::factory()->create([
        'mac_address' => '00:11:22:33:44:55',
        'api_key' => 'test-api-key',
    ]);

    $logData = [
        'log' => [
            'logs_array' => [
                ['message' => 'Test log message', 'level' => 'info'],
            ],
        ],
    ];

    $response = $this->withHeaders([
        'id' => $device->mac_address,
        'access-token' => $device->api_key,
    ])->postJson('/api/log', $logData);

    $response->assertOk()
        ->assertJson(['status' => '200']);

    expect($device->fresh()->last_log_request)
        ->toBe($logData);
});

// test('authenticated user can update device display', function () {
//    $user = User::factory()->create();
//    $device = Device::factory()->create(['user_id' => $user->id]);
//
//    Sanctum::actingAs($user, ['update-screen']);
//
//    $response = $this->postJson('/api/display/update', [
//        'device_id' => $device->id,
//        'markup' => '<div>Test markup</div>'
//    ]);
//
//    $response->assertOk();
// });

test('user cannot update display for devices they do not own', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $device = Device::factory()->create(['user_id' => $otherUser->id]);

    Sanctum::actingAs($user, ['update-screen']);

    $response = $this->postJson('/api/display/update', [
        'device_id' => $device->id,
        'markup' => '<div>Test markup</div>',
    ]);

    $response->assertForbidden();
});

test('invalid device credentials return error', function () {
    $response = $this->withHeaders([
        'id' => 'invalid-mac',
        'access-token' => 'invalid-token',
    ])->get('/api/display');

    $response->assertNotFound()
        ->assertJson(['message' => 'MAC Address not registered or invalid access token']);
});

test('log endpoint requires valid device credentials', function () {
    $response = $this->withHeaders([
        'id' => 'invalid-mac',
        'access-token' => 'invalid-token',
    ])->postJson('/api/log', ['log' => []]);

    $response->assertNotFound()
        ->assertJson(['message' => 'Device not found or invalid access token']);
});
