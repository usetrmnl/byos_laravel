<?php

use App\Jobs\FirmwareDownloadJob;
use App\Models\Firmware;
use Illuminate\Http\Client\ConnectionException;
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
    ]);

    (new FirmwareDownloadJob($firmware))->handle();

    expect($firmware->fresh()->storage_location)->toBe('firmwares/FW1.0.0.bin');
});
