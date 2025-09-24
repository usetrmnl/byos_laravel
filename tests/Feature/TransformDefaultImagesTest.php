<?php

use App\Models\Device;
use App\Models\DeviceModel;
use App\Services\ImageGenerationService;
use Illuminate\Support\Facades\Storage;

test('command transforms default images for all device models', function () {
    // Ensure we have device models
    $deviceModels = DeviceModel::all();
    expect($deviceModels)->not->toBeEmpty();

    // Run the command
    $this->artisan('images:generate-defaults')
        ->assertExitCode(0);

    // Check that the default-screens directory was created
    expect(Storage::disk('public')->exists('images/default-screens'))->toBeTrue();

    // Check that images were generated for each device model
    foreach ($deviceModels as $deviceModel) {
        $extension = $deviceModel->mime_type === 'image/bmp' ? 'bmp' : 'png';
        $filename = "{$deviceModel->width}_{$deviceModel->height}_{$deviceModel->bit_depth}_{$deviceModel->rotation}.{$extension}";

        $setupPath = "images/default-screens/setup-logo_{$filename}";
        $sleepPath = "images/default-screens/sleep_{$filename}";

        expect(Storage::disk('public')->exists($setupPath))->toBeTrue();
        expect(Storage::disk('public')->exists($sleepPath))->toBeTrue();
    }
});

test('getDeviceSpecificDefaultImage returns correct path for device with model', function () {
    $deviceModel = DeviceModel::first();
    expect($deviceModel)->not->toBeNull();

    $device = new Device();
    $device->deviceModel = $deviceModel;

    $setupImage = ImageGenerationService::getDeviceSpecificDefaultImage($device, 'setup-logo');
    $sleepImage = ImageGenerationService::getDeviceSpecificDefaultImage($device, 'sleep');

    expect($setupImage)->toContain('images/default-screens/setup-logo_');
    expect($sleepImage)->toContain('images/default-screens/sleep_');
});

test('getDeviceSpecificDefaultImage falls back to original images for device without model', function () {
    $device = new Device();
    $device->deviceModel = null;

    $setupImage = ImageGenerationService::getDeviceSpecificDefaultImage($device, 'setup-logo');
    $sleepImage = ImageGenerationService::getDeviceSpecificDefaultImage($device, 'sleep');

    expect($setupImage)->toBe('images/setup-logo.bmp');
    expect($sleepImage)->toBe('images/sleep.bmp');
});

test('generateDefaultScreenImage creates images from Blade templates', function () {
    $device = Device::factory()->create();

    $setupUuid = ImageGenerationService::generateDefaultScreenImage($device, 'setup-logo');
    $sleepUuid = ImageGenerationService::generateDefaultScreenImage($device, 'sleep');

    expect($setupUuid)->not->toBeEmpty();
    expect($sleepUuid)->not->toBeEmpty();
    expect($setupUuid)->not->toBe($sleepUuid);

    // Check that the generated images exist
    $setupPath = "images/generated/{$setupUuid}.png";
    $sleepPath = "images/generated/{$sleepUuid}.png";

    expect(Storage::disk('public')->exists($setupPath))->toBeTrue();
    expect(Storage::disk('public')->exists($sleepPath))->toBeTrue();
})->skipOnCI();

test('generateDefaultScreenImage throws exception for invalid image type', function () {
    $device = Device::factory()->create();

    expect(fn () => ImageGenerationService::generateDefaultScreenImage($device, 'invalid-type'))
        ->toThrow(InvalidArgumentException::class);
});

test('getDeviceSpecificDefaultImage returns null for invalid image type', function () {
    $device = new Device();
    $device->deviceModel = DeviceModel::first();

    $result = ImageGenerationService::getDeviceSpecificDefaultImage($device, 'invalid-type');
    expect($result)->toBeNull();
});
