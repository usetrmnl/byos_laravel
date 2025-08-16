<?php

declare(strict_types=1);

use App\Models\DeviceModel;
use App\Models\User;

it('allows a user to view the device models page', function (): void {
    $user = User::factory()->create();
    $deviceModels = DeviceModel::factory()->count(3)->create();

    $response = $this->actingAs($user)->get('/device-models');

    $response->assertSuccessful();
    $response->assertSee('Device Models');
    $response->assertSee('Add Device Model');

    foreach ($deviceModels as $deviceModel) {
        $response->assertSee($deviceModel->label);
        $response->assertSee((string) $deviceModel->width);
        $response->assertSee((string) $deviceModel->height);
        $response->assertSee((string) $deviceModel->bit_depth);
    }
});

it('allows creating a device model', function (): void {
    $user = User::factory()->create();

    $deviceModelData = [
        'name' => 'test-model',
        'label' => 'Test Model',
        'description' => 'A test device model',
        'width' => 800,
        'height' => 600,
        'colors' => 256,
        'bit_depth' => 8,
        'scale_factor' => 1.0,
        'rotation' => 0,
        'mime_type' => 'image/png',
        'offset_x' => 0,
        'offset_y' => 0,
    ];

    $deviceModel = DeviceModel::create($deviceModelData);

    $this->assertDatabaseHas('device_models', $deviceModelData);
    expect($deviceModel->name)->toBe($deviceModelData['name']);
});

it('allows updating a device model', function (): void {
    $user = User::factory()->create();
    $deviceModel = DeviceModel::factory()->create();

    $updatedData = [
        'name' => 'updated-model',
        'label' => 'Updated Model',
        'description' => 'An updated device model',
        'width' => 1024,
        'height' => 768,
        'colors' => 65536,
        'bit_depth' => 16,
        'scale_factor' => 1.5,
        'rotation' => 90,
        'mime_type' => 'image/jpeg',
        'offset_x' => 10,
        'offset_y' => 20,
    ];

    $deviceModel->update($updatedData);

    $this->assertDatabaseHas('device_models', $updatedData);
    expect($deviceModel->fresh()->name)->toBe($updatedData['name']);
});

it('allows deleting a device model', function (): void {
    $user = User::factory()->create();
    $deviceModel = DeviceModel::factory()->create();

    $deviceModelId = $deviceModel->id;
    $deviceModel->delete();

    $this->assertDatabaseMissing('device_models', ['id' => $deviceModelId]);
});

it('redirects unauthenticated users from the device models page', function (): void {
    $response = $this->get('/device-models');

    $response->assertRedirect('/login');
});
