<?php

use App\Jobs\FetchProxyCloudResponses;
use App\Models\Device;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    Storage::disk('public')->makeDirectory('/images/generated');
    Http::preventStrayRequests();
    Http::fake([
        'https://example.com/test-image.bmp*' => Http::response([], 200),
        'https://trmnl.app/api/log' => Http::response([], 200),
        'https://example.com/api/log' => Http::response([], 200),
    ]);
});

test('it fetches and processes proxy cloud responses for devices', function () {
    config(['services.trmnl.proxy_base_url' => 'https://example.com']);

    // Create a test device with proxy cloud enabled
    $device = Device::factory()->create([
        'proxy_cloud' => true,
        'mac_address' => '00:11:22:33:44:55',
        'api_key' => 'test-api-key',
        'last_rssi_level' => -70,
        'last_battery_voltage' => 3.7,
        'default_refresh_interval' => 300,
        'last_firmware_version' => '1.0.0',
    ]);

    // Mock the API response
    Http::fake([
        config('services.trmnl.proxy_base_url').'/api/display' => Http::response([
            'image_url' => 'https://example.com/test-image.bmp',
            'filename' => 'test-image',
        ]),
        'https://example.com/test-image.bmp' => Http::response('fake-image-content'),
    ]);

    Http::withHeaders([
        'id' => $device->mac_address,
        'access-token' => $device->api_key,
        'width' => 800,
        'height' => 480,
        'rssi' => $device->last_rssi_level,
        'battery_voltage' => $device->last_battery_voltage,
        'refresh-rate' => $device->default_refresh_interval,
        'fw-version' => $device->last_firmware_version,
        'accept-encoding' => 'identity;q=1,chunked;q=0.1,*;q=0',
        'user-agent' => 'ESP32HTTPClient',
    ])->get(config('services.trmnl.proxy_base_url').'/api/display');

    // Run the job
    $job = new FetchProxyCloudResponses;
    $job->handle();

    // Assert HTTP requests were made with correct headers
    Http::assertSent(function ($request) use ($device) {
        return $request->hasHeader('id', $device->mac_address) &&
            $request->hasHeader('access-token', $device->api_key) &&
            $request->hasHeader('width', 800) &&
            $request->hasHeader('height', 480) &&
            $request->hasHeader('rssi', $device->last_rssi_level) &&
            $request->hasHeader('battery_voltage', $device->last_battery_voltage) &&
            $request->hasHeader('refresh-rate', $device->default_refresh_interval) &&
            $request->hasHeader('fw-version', $device->last_firmware_version);
    });
    // Assert the device was updated
    $device->refresh();

    expect($device->current_screen_image)->toBe('test-image')
        ->and($device->proxy_cloud_response)->toBe([
            'image_url' => 'https://example.com/test-image.bmp',
            'filename' => 'test-image',
        ]);

    // Assert the image was saved
    Storage::disk('public')->assertExists('images/generated/test-image.bmp');
});

test('it handles log requests when present', function () {
    $device = Device::factory()->create([
        'proxy_cloud' => true,
        'mac_address' => '00:11:22:33:44:55',
        'api_key' => 'test-api-key',
        'last_log_request' => ['message' => 'test log'],
    ]);

    Http::fake([
        config('services.trmnl.proxy_base_url').'/api/display' => Http::response([
            'image_url' => 'https://example.com/test-image.bmp',
            'filename' => 'test-image',
        ]),
        'https://example.com/test-image.bmp' => Http::response('fake-image-content'),
        config('services.trmnl.proxy_base_url').'/api/log' => Http::response(null, 200),
    ]);

    $job = new FetchProxyCloudResponses;
    $job->handle();

    // Assert log request was sent
    Http::assertSent(function ($request) use ($device) {
        return $request->url() === config('services.trmnl.proxy_base_url').'/api/log' &&
            $request->hasHeader('id', $device->mac_address) &&
            $request->body() === json_encode(['message' => 'test log']);
    });

    // Assert log request was cleared
    $device->refresh();
    expect($device->last_log_request)->toBeNull();
});

test('it handles API errors gracefully', function () {
    $device = Device::factory()->create([
        'proxy_cloud' => true,
        'mac_address' => '00:11:22:33:44:55',
    ]);

    Http::fake([
        config('services.trmnl.proxy_base_url').'/api/display' => Http::response(null, 500),
    ]);

    $job = new FetchProxyCloudResponses;

    // Job should not throw exception but log error
    expect(fn () => $job->handle())->not->toThrow(Exception::class);
});

test('it only processes proxy cloud enabled devices', function () {
    Http::fake();
    $enabledDevice = Device::factory()->create(['proxy_cloud' => true]);
    $disabledDevice = Device::factory()->create(['proxy_cloud' => false]);

    $job = new FetchProxyCloudResponses;
    $job->handle();

    // Assert request was only made for enabled device
    Http::assertSent(function ($request) use ($enabledDevice) {
        return $request->hasHeader('id', $enabledDevice->mac_address);
    });

    Http::assertNotSent(function ($request) use ($disabledDevice) {
        return $request->hasHeader('id', $disabledDevice->mac_address);
    });
});

test('it fetches and processes proxy cloud responses for devices with BMP images', function () {
    config(['services.trmnl.proxy_base_url' => 'https://example.com']);

    // Create a test device with proxy cloud enabled
    $device = Device::factory()->create([
        'proxy_cloud' => true,
        'mac_address' => '00:11:22:33:44:55',
        'api_key' => 'test-api-key',
        'last_rssi_level' => -70,
        'last_battery_voltage' => 3.7,
        'default_refresh_interval' => 300,
        'last_firmware_version' => '1.0.0',
    ]);

    // Mock the API response with BMP image
    Http::fake([
        config('services.trmnl.proxy_base_url').'/api/display' => Http::response([
            'image_url' => 'https://example.com/test-image.bmp?response-content-type=image/bmp',
            'filename' => 'test-image',
        ]),
        'https://example.com/test-image.bmp?response-content-type=image/bmp' => Http::response('fake-image-content'),
    ]);

    // Run the job
    $job = new FetchProxyCloudResponses;
    $job->handle();

    // Assert HTTP requests were made with correct headers
    Http::assertSent(function ($request) use ($device) {
        return $request->hasHeader('id', $device->mac_address) &&
            $request->hasHeader('access-token', $device->api_key) &&
            $request->hasHeader('width', 800) &&
            $request->hasHeader('height', 480) &&
            $request->hasHeader('rssi', $device->last_rssi_level) &&
            $request->hasHeader('battery_voltage', $device->last_battery_voltage) &&
            $request->hasHeader('refresh-rate', $device->default_refresh_interval) &&
            $request->hasHeader('fw-version', $device->last_firmware_version);
    });

    // Assert the device was updated
    $device->refresh();

    expect($device->current_screen_image)->toBe('test-image')
        ->and($device->proxy_cloud_response)->toBe([
            'image_url' => 'https://example.com/test-image.bmp?response-content-type=image/bmp',
            'filename' => 'test-image',
        ]);

    // Assert the image was saved with BMP extension
    expect(Storage::disk('public')->exists('images/generated/test-image.bmp'))->toBeTrue();
    expect(Storage::disk('public')->exists('images/generated/test-image.png'))->toBeFalse();
});

test('it fetches and processes proxy cloud responses for devices with PNG images', function () {
    config(['services.trmnl.proxy_base_url' => 'https://example.com']);

    // Create a test device with proxy cloud enabled
    $device = Device::factory()->create([
        'proxy_cloud' => true,
        'mac_address' => '00:11:22:33:44:55',
        'api_key' => 'test-api-key',
        'last_rssi_level' => -70,
        'last_battery_voltage' => 3.7,
        'default_refresh_interval' => 300,
        'last_firmware_version' => '1.0.0',
    ]);

    // Mock the API response with PNG image
    Http::fake([
        config('services.trmnl.proxy_base_url').'/api/display' => Http::response([
            'image_url' => 'https://example.com/test-image.png?response-content-type=image/png',
            'filename' => 'test-image',
        ]),
        'https://example.com/test-image.png?response-content-type=image/png' => Http::response('fake-image-content'),
    ]);

    // Run the job
    $job = new FetchProxyCloudResponses;
    $job->handle();

    // Assert HTTP requests were made with correct headers
    Http::assertSent(function ($request) use ($device) {
        return $request->hasHeader('id', $device->mac_address) &&
            $request->hasHeader('access-token', $device->api_key) &&
            $request->hasHeader('width', 800) &&
            $request->hasHeader('height', 480) &&
            $request->hasHeader('rssi', $device->last_rssi_level) &&
            $request->hasHeader('battery_voltage', $device->last_battery_voltage) &&
            $request->hasHeader('refresh-rate', $device->default_refresh_interval) &&
            $request->hasHeader('fw-version', $device->last_firmware_version);
    });

    // Assert the device was updated
    $device->refresh();

    expect($device->current_screen_image)->toBe('test-image')
        ->and($device->proxy_cloud_response)->toBe([
            'image_url' => 'https://example.com/test-image.png?response-content-type=image/png',
            'filename' => 'test-image',
        ]);

    // Assert the image was saved with PNG extension
    expect(Storage::disk('public')->exists('images/generated/test-image.png'))->toBeTrue();
    expect(Storage::disk('public')->exists('images/generated/test-image.bmp'))->toBeFalse();
});

test('it handles missing content type in image URL gracefully', function () {
    config(['services.trmnl.proxy_base_url' => 'https://example.com']);

    // Create a test device with proxy cloud enabled
    $device = Device::factory()->create([
        'proxy_cloud' => true,
        'mac_address' => '00:11:22:33:44:55',
        'api_key' => 'test-api-key',
    ]);

    // Mock the API response with no content type in URL
    Http::fake([
        config('services.trmnl.proxy_base_url').'/api/display' => Http::response([
            'image_url' => 'https://example.com/test-image.bmp',
            'filename' => 'test-image',
        ]),
        'https://example.com/test-image.bmp' => Http::response('fake-image-content'),
    ]);

    // Run the job
    $job = new FetchProxyCloudResponses;
    $job->handle();

    // Assert the device was updated
    $device->refresh();

    expect($device->current_screen_image)->toBe('test-image')
        ->and($device->proxy_cloud_response)->toBe([
            'image_url' => 'https://example.com/test-image.bmp',
            'filename' => 'test-image',
        ]);

    // Assert the image was saved with default BMP extension
    expect(Storage::disk('public')->exists('images/generated/test-image.bmp'))->toBeTrue();
    expect(Storage::disk('public')->exists('images/generated/test-image.png'))->toBeFalse();
});
