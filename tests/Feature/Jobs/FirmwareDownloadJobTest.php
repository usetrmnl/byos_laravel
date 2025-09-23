<?php

use App\Jobs\FirmwareDownloadJob;
use App\Models\Firmware;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    Storage::disk('public')->makeDirectory('/firmwares');
});

test('it creates firmwares directory if it does not exist', function () {
    $firmware = Firmware::factory()->create([
        'url' => 'https://example.com/firmware.bin',
        'version_tag' => '1.0.0',
        'storage_location' => null,
    ]);

    Http::fake([
        'https://example.com/firmware.bin' => Http::response('fake firmware content', 200),
    ]);

    (new FirmwareDownloadJob($firmware))->handle();

    expect(Storage::disk('public')->exists('firmwares'))->toBeTrue();
});

test('it downloads firmware and updates storage location', function () {
    Http::fake([
        'https://example.com/firmware.bin' => Http::response('fake firmware content', 200),
    ]);

    $firmware = Firmware::factory()->create([
        'url' => 'https://example.com/firmware.bin',
        'version_tag' => '1.0.0',
        'storage_location' => null,
    ]);

    (new FirmwareDownloadJob($firmware))->handle();

    expect($firmware->fresh()->storage_location)->toBe('firmwares/FW1.0.0.bin');
});

test('it handles connection exception gracefully', function () {
    $firmware = Firmware::factory()->create([
        'url' => 'https://example.com/firmware.bin',
        'version_tag' => '1.0.0',
        'storage_location' => null,
    ]);

    Http::fake([
        'https://example.com/firmware.bin' => function () {
            throw new Illuminate\Http\Client\ConnectionException('Connection failed');
        },
    ]);

    Illuminate\Support\Facades\Log::shouldReceive('error')
        ->once()
        ->with('Firmware download failed: Connection failed');

    (new FirmwareDownloadJob($firmware))->handle();

    // Storage location should not be updated on failure
    expect($firmware->fresh()->storage_location)->toBeNull();
});

test('it handles general exception gracefully', function () {
    $firmware = Firmware::factory()->create([
        'url' => 'https://example.com/firmware.bin',
        'version_tag' => '1.0.0',
        'storage_location' => null,
    ]);

    Http::fake([
        'https://example.com/firmware.bin' => function () {
            throw new Exception('Unexpected error');
        },
    ]);

    Illuminate\Support\Facades\Log::shouldReceive('error')
        ->once()
        ->with('An unexpected error occurred: Unexpected error');

    (new FirmwareDownloadJob($firmware))->handle();

    // Storage location should not be updated on failure
    expect($firmware->fresh()->storage_location)->toBeNull();
});

test('it handles firmware with special characters in version tag', function () {
    Http::fake([
        'https://example.com/firmware.bin' => Http::response('fake firmware content', 200),
    ]);

    $firmware = Firmware::factory()->create([
        'url' => 'https://example.com/firmware.bin',
        'version_tag' => '1.0.0-beta',
    ]);

    (new FirmwareDownloadJob($firmware))->handle();

    expect($firmware->fresh()->storage_location)->toBe('firmwares/FW1.0.0-beta.bin');
});

test('it handles firmware with long version tag', function () {
    Http::fake([
        'https://example.com/firmware.bin' => Http::response('fake firmware content', 200),
    ]);

    $firmware = Firmware::factory()->create([
        'url' => 'https://example.com/firmware.bin',
        'version_tag' => '1.0.0.1234.5678.90',
    ]);

    (new FirmwareDownloadJob($firmware))->handle();

    expect($firmware->fresh()->storage_location)->toBe('firmwares/FW1.0.0.1234.5678.90.bin');
});

test('it creates firmwares directory even when it already exists', function () {
    $firmware = Firmware::factory()->create([
        'url' => 'https://example.com/firmware.bin',
        'version_tag' => '1.0.0',
        'storage_location' => null,
    ]);

    Http::fake([
        'https://example.com/firmware.bin' => Http::response('fake firmware content', 200),
    ]);

    // Directory already exists from beforeEach
    expect(Storage::disk('public')->exists('firmwares'))->toBeTrue();

    (new FirmwareDownloadJob($firmware))->handle();

    // Should still work fine
    expect($firmware->fresh()->storage_location)->toBe('firmwares/FW1.0.0.bin');
});

test('it handles http error response', function () {
    $firmware = Firmware::factory()->create([
        'url' => 'https://example.com/firmware.bin',
        'version_tag' => '1.0.0',
        'storage_location' => null,
    ]);

    Http::fake([
        'https://example.com/firmware.bin' => Http::response('Not Found', 404),
    ]);

    Illuminate\Support\Facades\Log::shouldReceive('error')
        ->once()
        ->with(Mockery::type('string'));

    (new FirmwareDownloadJob($firmware))->handle();

    // Storage location should not be updated on failure
    expect($firmware->fresh()->storage_location)->toBeNull();
});
