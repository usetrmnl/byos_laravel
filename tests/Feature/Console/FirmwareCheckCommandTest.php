<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('firmware check command has correct signature', function (): void {
    $command = $this->app->make(App\Console\Commands\FirmwareCheckCommand::class);

    expect($command->getName())->toBe('trmnl:firmware:check');
    expect($command->getDescription())->toBe('Checks for the latest firmware and downloads it if flag --download is passed.');
});

test('firmware check command runs without errors', function (): void {
    // Mock the firmware API response
    $baseUrl = config('services.trmnl.base_url');

    Http::fake([
        $baseUrl.'/api/firmware/latest' => Http::response([
            'version' => '1.0.0',
            'url' => 'https://example.com/firmware.bin',
        ], 200),
    ]);

    $this->artisan('trmnl:firmware:check')
        ->assertExitCode(0);

    // Verify that the firmware was created
    expect(App\Models\Firmware::where('version_tag', '1.0.0')->exists())->toBeTrue();
});

test('firmware check command runs with download flag', function (): void {
    // Mock the firmware API response
    $baseUrl = config('services.trmnl.base_url');

    Http::fake([
        $baseUrl.'/api/firmware/latest' => Http::response([
            'version' => '1.0.0',
            'url' => 'https://example.com/firmware.bin',
        ], 200),
        'https://example.com/firmware.bin' => Http::response('fake firmware content', 200),
    ]);

    // Mock storage to prevent actual file operations
    Storage::fake('public');

    $this->artisan('trmnl:firmware:check', ['--download' => true])
        ->assertExitCode(0);

    // Verify that the firmware was created and marked as latest
    expect(App\Models\Firmware::where('version_tag', '1.0.0')->exists())->toBeTrue();

    // Verify that the firmware was downloaded (storage_location should be set)
    $firmware = App\Models\Firmware::where('version_tag', '1.0.0')->first();
    expect($firmware->storage_location)->toBe('firmwares/FW1.0.0.bin');
});

test('firmware check command can run successfully', function (): void {
    // Mock the firmware API response
    $baseUrl = config('services.trmnl.base_url');

    Http::fake([
        $baseUrl.'/api/firmware/latest' => Http::response([
            'version' => '1.0.0',
            'url' => 'https://example.com/firmware.bin',
        ], 200),
    ]);

    $this->artisan('trmnl:firmware:check')
        ->assertExitCode(0);

    // Verify that the firmware was created
    expect(App\Models\Firmware::where('version_tag', '1.0.0')->exists())->toBeTrue();
});
