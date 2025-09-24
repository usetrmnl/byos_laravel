<?php

use App\Models\Device;
use App\Models\DeviceModel;
use App\Services\ImageGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('public');
    Storage::disk('public')->makeDirectory('/images/generated');
});

it('generates 4-color 2-bit PNG with device model', function (): void {
    // Create a DeviceModel for 4-color, 2-bit PNG
    $deviceModel = DeviceModel::factory()->create([
        'width' => 800,
        'height' => 480,
        'colors' => 4,
        'bit_depth' => 2,
        'scale_factor' => 1.0,
        'rotation' => 0,
        'mime_type' => 'image/png',
        'offset_x' => 0,
        'offset_y' => 0,
    ]);

    // Create a device with the DeviceModel
    $device = Device::factory()->create([
        'device_model_id' => $deviceModel->id,
    ]);

    $markup = '<div style="background: white; color: black; padding: 20px;">Test Content</div>';
    $uuid = ImageGenerationService::generateImage($markup, $device->id);

    // Assert the device was updated with a new image UUID
    $device->refresh();
    expect($device->current_screen_image)->toBe($uuid);

    // Assert PNG file was created
    Storage::disk('public')->assertExists("/images/generated/{$uuid}.png");

    // Verify the image file has content and isn't blank
    $imagePath = Storage::disk('public')->path("/images/generated/{$uuid}.png");
    $imageSize = filesize($imagePath);
    expect($imageSize)->toBeGreaterThan(200); // Should be at least 200 bytes for a 2-bit PNG

    // Verify it's a valid PNG file
    $imageInfo = getimagesize($imagePath);
    expect($imageInfo[0])->toBe(800); // Width
    expect($imageInfo[1])->toBe(480); // Height
    expect($imageInfo[2])->toBe(IMAGETYPE_PNG); // PNG type

})->skipOnCI();
