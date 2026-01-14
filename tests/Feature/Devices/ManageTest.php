<?php

use App\Models\Device;
use App\Models\User;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

test('device management page can be rendered', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get('/devices');

    $response->assertOk();
});

test('user can create a new device', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $deviceData = [
        'name' => 'Test Device',
        'mac_address' => '00:11:22:33:44:55',
        'api_key' => 'test-api-key',
        'default_refresh_interval' => 900,
        'friendly_id' => 'test-device-1',
    ];

    $response = Livewire::test('devices.manage')
        ->set('name', $deviceData['name'])
        ->set('mac_address', $deviceData['mac_address'])
        ->set('api_key', $deviceData['api_key'])
        ->set('default_refresh_interval', $deviceData['default_refresh_interval'])
        ->set('friendly_id', $deviceData['friendly_id'])
        ->call('createDevice');

    $response->assertHasNoErrors();

    expect(Device::count())->toBe(1);

    $device = Device::first();
    expect($device->name)->toBe($deviceData['name']);
    expect($device->mac_address)->toBe($deviceData['mac_address']);
    expect($device->api_key)->toBe($deviceData['api_key']);
    expect($device->default_refresh_interval)->toBe($deviceData['default_refresh_interval']);
    expect($device->friendly_id)->toBe($deviceData['friendly_id']);
    expect($device->user_id)->toBe($user->id);
});

test('device creation requires required fields', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = Livewire::test('devices.manage')
        ->set('name', '')
        ->set('mac_address', '')
        ->set('api_key', '')
        ->set('default_refresh_interval', '')
        ->set('friendly_id', '')
        ->call('createDevice');

    $response->assertHasErrors([
        'mac_address',
        'api_key',
        'default_refresh_interval',
    ]);
});

test('user can toggle proxy cloud for their device', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);
    $device = Device::factory()->create([
        'user_id' => $user->id,
        'proxy_cloud' => false,
    ]);

    $response = Livewire::test('devices.manage')
        ->call('toggleProxyCloud', $device);

    $response->assertHasNoErrors();
    expect($device->fresh()->proxy_cloud)->toBeTrue();

    // Toggle back to false
    $response = Livewire::test('devices.manage')
        ->call('toggleProxyCloud', $device);

    expect($device->fresh()->proxy_cloud)->toBeFalse();
});

test('user cannot toggle proxy cloud for other users devices', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $otherUser = User::factory()->create();
    $device = Device::factory()->create([
        'user_id' => $otherUser->id,
        'proxy_cloud' => false,
    ]);

    $response = Livewire::test('devices.manage')
        ->call('toggleProxyCloud', $device);

    $response->assertStatus(403);
    expect($device->fresh()->proxy_cloud)->toBeFalse();
});
