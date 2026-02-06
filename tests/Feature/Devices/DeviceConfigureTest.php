<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

test('configure view displays last_refreshed_at timestamp', function (): void {
    $user = User::factory()->create();
    $device = Device::factory()->create([
        'user_id' => $user->id,
        'last_refreshed_at' => now()->subMinutes(5),
    ]);

    $response = actingAs($user)
        ->get(route('devices.configure', $device));

    $response->assertOk()
        ->assertSee('5 minutes ago');
});

test('configure edit modal shows mirror checkbox and allows unchecking mirror', function (): void {
    $user = User::factory()->create();
    actingAs($user);

    $deviceAttributes = [
        'user_id' => $user->id,
        'width' => 800,
        'height' => 480,
        'rotate' => 0,
        'image_format' => 'png',
        'maximum_compatibility' => false,
    ];
    $sourceDevice = Device::factory()->create($deviceAttributes);
    $mirrorDevice = Device::factory()->create([
        ...$deviceAttributes,
        'mirror_device_id' => $sourceDevice->id,
    ]);

    $response = $this->get(route('devices.configure', $mirrorDevice));
    $response->assertOk()
        ->assertSee('Mirrors Device')
        ->assertSee('Select Device to Mirror');

    Livewire::test('devices.configure', ['device' => $mirrorDevice])
        ->set('is_mirror', false)
        ->call('updateDevice')
        ->assertHasNoErrors();

    $mirrorDevice->refresh();
    expect($mirrorDevice->mirror_device_id)->toBeNull();
});
