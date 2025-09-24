<?php

use App\Enums\ImageFormat;
use App\Models\Device;
use App\Models\Playlist;
use App\Models\PlaylistItem;
use App\Models\Plugin;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('public');
    Storage::disk('public')->makeDirectory('/images/generated');
});

test('device with firmware version 1.5.1 gets bmp format', function (): void {
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

test('device with firmware version 1.5.2 gets png format', function (): void {
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

test('device falls back to bmp when png does not exist', function (): void {
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

test('device without device_model_id and image_format bmp3_1bit_srgb returns bmp when plugin is rendered', function (): void {
    // Create a user with auto-assign enabled
    $user = User::factory()->create([
        'assign_new_devices' => true,
    ]);

    // Create a device without device_model_id and with bmp3_1bit_srgb format
    $device = Device::factory()->create([
        'user_id' => $user->id,
        'mac_address' => '00:11:22:33:44:55',
        'api_key' => 'test-api-key',
        'device_model_id' => null, // Explicitly set to null
        'image_format' => ImageFormat::BMP3_1BIT_SRGB->value,
        'last_firmware_version' => '1.5.2',
    ]);

    // Create a plugin
    $plugin = Plugin::factory()->create([
        'user_id' => $user->id,
        'name' => 'Test Plugin',
        'render_markup' => '<div>Test Content</div>',
        'data_strategy' => 'static',
        'markup_language' => 'blade',
        'current_image' => 'test-generated-image', // Set current image directly
    ]);

    // Create a playlist for the device
    $playlist = Playlist::factory()->create([
        'device_id' => $device->id,
        'is_active' => true,
        'refresh_time' => 900,
    ]);

    // Create a playlist item with the plugin
    $playlistItem = PlaylistItem::factory()->create([
        'playlist_id' => $playlist->id,
        'plugin_id' => $plugin->id,
        'is_active' => true,
        'order' => 1,
    ]);

    // Mock the image generation to create both bmp and png files
    $imageUuid = 'test-generated-image';
    Storage::disk('public')->put('images/generated/'.$imageUuid.'.bmp', 'fake bmp content');
    Storage::disk('public')->put('images/generated/'.$imageUuid.'.png', 'fake png content');

    // Set the device's current screen image to the plugin's image
    $device->update(['current_screen_image' => $imageUuid]);

    // Test /api/display endpoint
    $displayResponse = $this->withHeaders([
        'id' => $device->mac_address,
        'access-token' => $device->api_key,
        'rssi' => -70,
        'battery_voltage' => 3.8,
        'fw-version' => '1.5.2',
    ])->get('/api/current_screen');

    $displayResponse->assertOk();
    $displayResponse->assertJson([
        'filename' => $imageUuid.'.bmp',
    ]);

    // Verify that the device's image_format is correctly set
    $device->refresh();
    expect($device->image_format)->toBe(ImageFormat::BMP3_1BIT_SRGB->value)
        ->and($device->device_model_id)->toBeNull();
});
