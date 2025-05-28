<?php

use App\Jobs\FirmwarePollJob;
use App\Models\Firmware;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

test('it creates new firmware record when polling', function () {
    Http::fake([
        'usetrmnl.com/api/firmware/latest' => Http::response([
            'version' => '1.0.0',
            'url' => 'https://example.com/firmware.bin'
        ], 200)
    ]);

    (new FirmwarePollJob())->handle();

    expect(Firmware::where('version_tag', '1.0.0')->exists())->toBeTrue()
        ->and(Firmware::where('version_tag', '1.0.0')->first())
        ->url->toBe('https://example.com/firmware.bin')
        ->latest->toBeTrue();
});

test('it updates existing firmware record when polling', function () {
    $existingFirmware = Firmware::factory()->create([
        'version_tag' => '1.0.0',
        'url' => 'https://old-url.com/firmware.bin',
        'latest' => true
    ]);

    Http::fake([
        'usetrmnl.com/api/firmware/latest' => Http::response([
            'version' => '1.0.0',
            'url' => 'https://new-url.com/firmware.bin'
        ], 200)
    ]);

    (new FirmwarePollJob())->handle();

    expect($existingFirmware->fresh())
        ->url->toBe('https://new-url.com/firmware.bin')
        ->latest->toBeTrue();
});

test('it marks previous firmware as not latest when new version is found', function () {
    $oldFirmware = Firmware::factory()->create([
        'version_tag' => '1.0.0',
        'latest' => true
    ]);

    Http::fake([
        'usetrmnl.com/api/firmware/latest' => Http::response([
            'version' => '1.1.0',
            'url' => 'https://example.com/firmware.bin'
        ], 200)
    ]);

    (new FirmwarePollJob())->handle();

    expect($oldFirmware->fresh()->latest)->toBeFalse()
        ->and(Firmware::where('version_tag', '1.1.0')->first()->latest)->toBeTrue();
});

test('it handles connection exception gracefully', function () {
    Http::fake([
        'usetrmnl.com/api/firmware/latest' => function () {
            throw new ConnectionException('Connection failed');
        }
    ]);

    (new FirmwarePollJob())->handle();

    // Verify no firmware records were created
    expect(Firmware::count())->toBe(0);
});

test('it handles invalid response gracefully', function () {
    Http::fake([
        'usetrmnl.com/api/firmware/latest' => Http::response(null, 200)
    ]);

    (new FirmwarePollJob())->handle();

    // Verify no firmware records were created
    expect(Firmware::count())->toBe(0);
});

test('it handles missing version in response gracefully', function () {
    Http::fake([
        'usetrmnl.com/api/firmware/latest' => Http::response([
            'url' => 'https://example.com/firmware.bin'
        ], 200)
    ]);

    (new FirmwarePollJob())->handle();

    // Verify no firmware records were created
    expect(Firmware::count())->toBe(0);
});

test('it handles missing url in response gracefully', function () {
    Http::fake([
        'usetrmnl.com/api/firmware/latest' => Http::response([
            'version' => '1.0.0'
        ], 200)
    ]);

    (new FirmwarePollJob())->handle();

    // Verify no firmware records were created
    expect(Firmware::count())->toBe(0);
});
