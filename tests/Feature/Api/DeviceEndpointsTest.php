<?php

use App\Models\Device;
use App\Models\Playlist;
use App\Models\PlaylistItem;
use App\Models\Plugin;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    Storage::disk('public')->makeDirectory('/images/generated');
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

test('new device is auto-assigned and mirrors specified device', function () {
    // Create a source device that will be mirrored
    $sourceDevice = Device::factory()->create([
        'mac_address' => 'AA:BB:CC:DD:EE:FF',
        'api_key' => 'source-api-key',
        'current_screen_image' => 'source-image',
    ]);

    // Create user with auto-assign enabled and mirror device set
    $user = User::factory()->create([
        'assign_new_devices' => true,
        'assign_new_device_id' => $sourceDevice->id,
    ]);

    // Make request from new device
    $response = $this->withHeaders([
        'id' => '00:11:22:33:44:55',
        'access-token' => 'new-device-key',
        'rssi' => -70,
        'battery_voltage' => 3.8,
        'fw-version' => '1.0.0',
    ])->get('/api/display');

    $response->assertOk();

    // Verify the new device was created and mirrors the source device
    $newDevice = Device::where('mac_address', '00:11:22:33:44:55')->first();
    expect($newDevice)
        ->not->toBeNull()
        ->user_id->toBe($user->id)
        ->api_key->toBe('new-device-key')
        ->mirror_device_id->toBe($sourceDevice->id);

    // Verify the response contains the source device's image
    $response->assertJson([
        'filename' => 'source-image.bmp',
    ]);
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

test('update_firmware flag is only returned once', function () {
    $device = Device::factory()->create([
        'mac_address' => '00:11:22:33:44:55',
        'api_key' => 'test-api-key',
        'proxy_cloud_response' => [
            'update_firmware' => true,
            'firmware_url' => 'https://example.com/firmware.bin',
        ],
    ]);

    // First request should return update_firmware as true
    $response = $this->withHeaders([
        'id' => $device->mac_address,
        'access-token' => $device->api_key,
        'rssi' => -70,
        'battery_voltage' => 3.8,
        'fw-version' => '1.0.0',
    ])->get('/api/display');

    $response->assertOk()
        ->assertJson([
            'update_firmware' => true,
            'firmware_url' => 'https://example.com/firmware.bin',
        ]);

    // Second request should return update_firmware as false
    $response = $this->withHeaders([
        'id' => $device->mac_address,
        'access-token' => $device->api_key,
        'rssi' => -70,
        'battery_voltage' => 3.8,
        'fw-version' => '1.0.0',
    ])->get('/api/display');

    $response->assertOk()
        ->assertJson([
            'update_firmware' => false,
            'firmware_url' => 'https://example.com/firmware.bin',
        ]);

    // Verify the proxy_cloud_response was updated
    $device->refresh();
    expect($device->proxy_cloud_response['update_firmware'])->toBeFalse();
});

test('authenticated user can fetch device status', function () {
    $user = User::factory()->create();
    $device = Device::factory()->create([
        'user_id' => $user->id,
        'mac_address' => '00:11:22:33:44:55',
        'name' => 'Test Device',
        'friendly_id' => 'test-device',
        'last_rssi_level' => -70,
        'last_battery_voltage' => 3.8,
        'last_firmware_version' => '1.0.0',
        'current_screen_image' => 'test-image',
        'default_refresh_interval' => 900,
    ]);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/display/status?device_id='.$device->id);

    $response->assertOk()
        ->assertJson([
            'id' => $device->id,
            'mac_address' => '00:11:22:33:44:55',
            'name' => 'Test Device',
            'friendly_id' => 'test-device',
            'last_rssi_level' => -70,
            'last_battery_voltage' => 3.8,
            'last_firmware_version' => '1.0.0',
            'battery_percent' => 67,
            'wifi_strength' => 2,
            'current_screen_image' => 'test-image',
            'default_refresh_interval' => 900,
        ]);
});

test('user cannot fetch status for devices they do not own', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $device = Device::factory()->create(['user_id' => $otherUser->id]);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/display/status?device_id='.$device->id);

    $response->assertForbidden();
});

test('display status endpoint requires device_id parameter', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->getJson('/api/display/status');

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['device_id']);
});

test('display status endpoint requires valid device_id', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->getJson('/api/display/status?device_id=999');

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['device_id']);
});

test('device can mirror another device', function () {
    // Create source device with a playlist and image
    $sourceDevice = Device::factory()->create([
        'mac_address' => '00:11:22:33:44:55',
        'api_key' => 'source-api-key',
        'current_screen_image' => 'source-image',
    ]);

    // Create mirroring device
    $mirrorDevice = Device::factory()->create([
        'mac_address' => 'AA:BB:CC:DD:EE:FF',
        'api_key' => 'mirror-api-key',
        'mirror_device_id' => $sourceDevice->id,
    ]);

    // Make request from mirror device
    $response = $this->withHeaders([
        'id' => $mirrorDevice->mac_address,
        'access-token' => $mirrorDevice->api_key,
        'rssi' => -70,
        'battery_voltage' => 3.8,
        'fw-version' => '1.0.0',
    ])->get('/api/display');

    $response->assertOk()
        ->assertJson([
            'status' => '0',
            'filename' => 'source-image.bmp',
            'refresh_rate' => 900,
            'reset_firmware' => false,
            'update_firmware' => false,
            'firmware_url' => null,
            'special_function' => 'sleep',
        ]);

    // Verify mirror device stats were updated
    expect($mirrorDevice->fresh())
        ->last_rssi_level->toBe(-70)
        ->last_battery_voltage->toBe(3.8)
        ->last_firmware_version->toBe('1.0.0');
});

test('device can fetch current screen data', function () {
    $device = Device::factory()->create([
        'mac_address' => '00:11:22:33:44:55',
        'api_key' => 'test-api-key',
        'current_screen_image' => 'test-image',
    ]);

    $response = $this->withHeaders([
        'access-token' => $device->api_key,
    ])->get('/api/current_screen');

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
});

test('current_screen endpoint requires valid device credentials', function () {
    $response = $this->withHeaders([
        'access-token' => 'invalid-token',
    ])->get('/api/current_screen');

    $response->assertNotFound()
        ->assertJson(['message' => 'Device not found or invalid access token']);
});

test('authenticated user can fetch their devices', function () {
    $user = User::factory()->create();
    $devices = Device::factory()->count(2)->create([
        'user_id' => $user->id,
        'last_battery_voltage' => 3.72,
        'last_rssi_level' => -63,
    ]);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/devices');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'friendly_id',
                    'mac_address',
                    'battery_voltage',
                    'rssi'
                ]
            ]
        ])
        ->assertJsonCount(2, 'data');

    // Verify the first device's data
    $response->assertJson([
        'data' => [
            [
                'id' => $devices[0]->id,
                'name' => $devices[0]->name,
                'friendly_id' => $devices[0]->friendly_id,
                'mac_address' => $devices[0]->mac_address,
                'battery_voltage' => 3.72,
                'rssi' => -63
            ]
        ]
    ]);
});

test('plugin doesn\'t update image unless required', function () {
    // Create source device with a playlist
    $device = Device::factory()->create([
        'mac_address' => '55:11:22:33:44:55',
        'api_key' => 'source-api-key',
        'proxy_cloud' => false,
    ]);

    $plugin = Plugin::factory()->create([
        'name' => 'Zen Quotes',
        'polling_url' => 'https://zenquotes.io/api/today',
        'data_stale_minutes' => 1,
        'data_strategy' => 'polling',
        'polling_verb' => 'get',
        'render_markup_view' => 'recipes.zen',
        'is_native' => false,
        'data_payload_updated_at' => null,
    ]);

    $playlist = Playlist::factory()->create([
        'device_id' => $device->id,
        'name' => 'update_test',
        'is_active' => true,
        'weekdays' => null,
        'active_from' => null,
        'active_until' => null,
    ]);

    PlaylistItem::factory()->create([
        'playlist_id' => $playlist->id,
        'plugin_id' => $plugin->id,
        'order' => 1,
        'is_active' => true,
        'last_displayed_at' => null,
    ]);

    // initial request, generates the image
    $firstResponse = $this->withHeaders([
        'id' => $device->mac_address,
        'access-token' => $device->api_key,
        'rssi' => -70,
        'battery_voltage' => 3.8,
        'fw-version' => '1.0.0',
    ])->get('/api/display');

    $firstResponse->assertOk();
    expect($firstResponse['filename'])->not->toBe('setup-logo.bmp');

    // second request after 15 seconds, shouldn't generate a new image
    $plugin->update(['data_payload_updated_at' => now()->addSeconds(-15)]);
    $secondResponse = $this->withHeaders([
        'id' => $device->mac_address,
        'access-token' => $device->api_key,
        'rssi' => -70,
        'battery_voltage' => 3.8,
        'fw-version' => '1.0.0',
    ])->get('/api/display');

    expect($secondResponse['filename'])
        ->toBe($firstResponse['filename']);

    // third request after 75 seconds, should generate a new image
    $plugin->update(['data_payload_updated_at' => now()->addSeconds(-75)]);
    $thirdResponse = $this->withHeaders([
        'id' => $device->mac_address,
        'access-token' => $device->api_key,
        'rssi' => -70,
        'battery_voltage' => 3.8,
        'fw-version' => '1.0.0',
    ])->get('/api/display');

    expect($thirdResponse['filename'])
        ->not->toBe($firstResponse['filename']);
})->skipOnGitHubActions();
