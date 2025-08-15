<?php

declare(strict_types=1);

use App\Models\{Device, User};
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('dashboard shows device image with correct rotation', function () {
    $user = User::factory()->create();
    $device = Device::factory()->create([
        'user_id' => $user->id,
        'rotate' => 90,
        'current_screen_image' => 'test-image-uuid',
    ]);

    // Mock the file existence check
    \Illuminate\Support\Facades\Storage::fake('public');
    \Illuminate\Support\Facades\Storage::disk('public')->put('images/generated/test-image-uuid.png', 'fake-image-content');

    $response = $this->actingAs($user)
        ->get(route('dashboard'));

    $response->assertSuccessful();
    $response->assertSee('-rotate-[90deg]');
    $response->assertSee('origin-center');
});

test('device configure page shows device image with correct rotation', function () {
    $user = User::factory()->create();
    $device = Device::factory()->create([
        'user_id' => $user->id,
        'rotate' => 90,
        'current_screen_image' => 'test-image-uuid',
    ]);

    // Mock the file existence check
    \Illuminate\Support\Facades\Storage::fake('public');
    \Illuminate\Support\Facades\Storage::disk('public')->put('images/generated/test-image-uuid.png', 'fake-image-content');

    $response = $this->actingAs($user)
        ->get(route('devices.configure', $device));

    $response->assertSuccessful();
    $response->assertSee('-rotate-[90deg]');
    $response->assertSee('origin-center');
});

test('device with no rotation shows no transform style', function () {
    $user = User::factory()->create();
    $device = Device::factory()->create([
        'user_id' => $user->id,
        'rotate' => 0,
        'current_screen_image' => 'test-image-uuid',
    ]);

    // Mock the file existence check
    \Illuminate\Support\Facades\Storage::fake('public');
    \Illuminate\Support\Facades\Storage::disk('public')->put('images/generated/test-image-uuid.png', 'fake-image-content');

    $response = $this->actingAs($user)
        ->get(route('dashboard'));

    $response->assertSuccessful();
    $response->assertSee('-rotate-[0deg]');
});

test('device with null rotation defaults to 0', function () {
    $user = User::factory()->create();
    $device = Device::factory()->create([
        'user_id' => $user->id,
        'rotate' => null,
        'current_screen_image' => 'test-image-uuid',
    ]);

    // Mock the file existence check
    \Illuminate\Support\Facades\Storage::fake('public');
    \Illuminate\Support\Facades\Storage::disk('public')->put('images/generated/test-image-uuid.png', 'fake-image-content');

    $response = $this->actingAs($user)
        ->get(route('dashboard'));

    $response->assertSuccessful();
    $response->assertSee('-rotate-[0deg]');
});
