<?php

use App\Jobs\GenerateScreenJob;
use App\Models\Device;
use Illuminate\Support\Facades\Storage;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    Storage::disk('public')->makeDirectory('/images/generated');
});

test('it generates screen images and updates device', function () {
    $device = Device::factory()->create();
    $job = new GenerateScreenJob($device->id, null, view('trmnl')->render());
    $job->handle();

    // Assert the device was updated with a new image UUID
    $device->refresh();
    expect($device->current_screen_image)->not->toBeNull();

    // Assert both PNG and BMP files were created
    $uuid = $device->current_screen_image;
    Storage::disk('public')->assertExists("/images/generated/{$uuid}.png");
})->skipOnGitHubActions();

test('it cleans up unused images', function () {
    // Create some test devices with images
    $activeDevice = Device::factory()->create([
        'current_screen_image' => 'uuid-to-be-replaced',
    ]);

    // Create some test files
    Storage::disk('public')->put('/images/generated/uuid-to-be-replaced.png', 'test');
    Storage::disk('public')->put('/images/generated/uuid-to-be-replaced.bmp', 'test');
    Storage::disk('public')->put('/images/generated/inactive-uuid.png', 'test');
    Storage::disk('public')->put('/images/generated/inactive-uuid.bmp', 'test');

    // Run a job which will trigger cleanup
    $job = new GenerateScreenJob($activeDevice->id, null, '<div>Test</div>');
    $job->handle();

    Storage::disk('public')->assertMissing('/images/generated/uuid-to-be-replaced.png');
    Storage::disk('public')->assertMissing('/images/generated/uuid-to-be-replaced.bmp');
    Storage::disk('public')->assertMissing('/images/generated/inactive-uuid.png');
    Storage::disk('public')->assertMissing('/images/generated/inactive-uuid.bmp');
})->skipOnGitHubActions();

test('it preserves gitignore file during cleanup', function () {
    Storage::disk('public')->put('/images/generated/.gitignore', '*');

    $device = Device::factory()->create();
    $job = new GenerateScreenJob($device->id, null, '<div>Test</div>');
    $job->handle();

    Storage::disk('public')->assertExists('/images/generated/.gitignore');
})->skipOnGitHubActions();
