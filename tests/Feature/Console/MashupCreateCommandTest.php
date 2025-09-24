<?php

declare(strict_types=1);

use App\Models\Device;
use App\Models\Playlist;
use App\Models\PlaylistItem;
use App\Models\Plugin;
use App\Models\User;

test('mashup create command has correct signature', function (): void {
    $this->artisan('mashup:create --help')
        ->assertExitCode(0);
});

test('mashup create command creates mashup successfully', function (): void {
    $user = User::factory()->create();
    $device = Device::factory()->create(['user_id' => $user->id]);
    $playlist = Playlist::factory()->create(['device_id' => $device->id]);
    $plugin1 = Plugin::factory()->create(['user_id' => $user->id]);
    $plugin2 = Plugin::factory()->create(['user_id' => $user->id]);

    $this->artisan('mashup:create')
        ->expectsQuestion('Select a device', $device->id)
        ->expectsQuestion('Select a playlist', $playlist->id)
        ->expectsQuestion('Select a layout', '1Lx1R')
        ->expectsQuestion('Enter a name for this mashup', 'Test Mashup')
        ->expectsQuestion('Select the first plugin', $plugin1->id)
        ->expectsQuestion('Select the second plugin', $plugin2->id)
        ->expectsOutput('Mashup created successfully!')
        ->assertExitCode(0);

    $playlistItem = PlaylistItem::where('playlist_id', $playlist->id)
        ->whereJsonContains('mashup->mashup_name', 'Test Mashup')
        ->first();

    expect($playlistItem)->not->toBeNull();
    expect($playlistItem->isMashup())->toBeTrue();
    expect($playlistItem->getMashupLayoutType())->toBe('1Lx1R');
    expect($playlistItem->getMashupPluginIds())->toContain($plugin1->id, $plugin2->id);
});

test('mashup create command exits when no devices found', function (): void {
    $this->artisan('mashup:create')
        ->expectsOutput('No devices found. Please create a device first.')
        ->assertExitCode(1);
});

test('mashup create command exits when no playlists found for device', function (): void {
    $user = User::factory()->create();
    $device = Device::factory()->create(['user_id' => $user->id]);

    $this->artisan('mashup:create')
        ->expectsQuestion('Select a device', $device->id)
        ->expectsOutput('No playlists found for this device. Please create a playlist first.')
        ->assertExitCode(1);
});

test('mashup create command exits when no plugins found', function (): void {
    $user = User::factory()->create();
    $device = Device::factory()->create(['user_id' => $user->id]);
    $playlist = Playlist::factory()->create(['device_id' => $device->id]);

    $this->artisan('mashup:create')
        ->expectsQuestion('Select a device', $device->id)
        ->expectsQuestion('Select a playlist', $playlist->id)
        ->expectsQuestion('Select a layout', '1Lx1R')
        ->expectsQuestion('Enter a name for this mashup', 'Test Mashup')
        ->expectsOutput('No plugins found. Please create some plugins first.')
        ->assertExitCode(1);
});

test('mashup create command validates mashup name length', function (): void {
    $user = User::factory()->create();
    $device = Device::factory()->create(['user_id' => $user->id]);
    $playlist = Playlist::factory()->create(['device_id' => $device->id]);
    $plugin1 = Plugin::factory()->create(['user_id' => $user->id]);
    $plugin2 = Plugin::factory()->create(['user_id' => $user->id]);

    $this->artisan('mashup:create')
        ->expectsQuestion('Select a device', $device->id)
        ->expectsQuestion('Select a playlist', $playlist->id)
        ->expectsQuestion('Select a layout', '1Lx1R')
        ->expectsQuestion('Enter a name for this mashup', 'A') // Too short
        ->expectsOutput('The name must be at least 2 characters.')
        ->assertExitCode(1);
});

test('mashup create command validates mashup name maximum length', function (): void {
    $user = User::factory()->create();
    $device = Device::factory()->create(['user_id' => $user->id]);
    $playlist = Playlist::factory()->create(['device_id' => $device->id]);
    $plugin1 = Plugin::factory()->create(['user_id' => $user->id]);
    $plugin2 = Plugin::factory()->create(['user_id' => $user->id]);

    $longName = str_repeat('A', 51); // Too long

    $this->artisan('mashup:create')
        ->expectsQuestion('Select a device', $device->id)
        ->expectsQuestion('Select a playlist', $playlist->id)
        ->expectsQuestion('Select a layout', '1Lx1R')
        ->expectsQuestion('Enter a name for this mashup', $longName)
        ->expectsOutput('The name must not exceed 50 characters.')
        ->assertExitCode(1);
});

test('mashup create command uses default name when provided', function (): void {
    $user = User::factory()->create();
    $device = Device::factory()->create(['user_id' => $user->id]);
    $playlist = Playlist::factory()->create(['device_id' => $device->id]);
    $plugin1 = Plugin::factory()->create(['user_id' => $user->id]);
    $plugin2 = Plugin::factory()->create(['user_id' => $user->id]);

    $this->artisan('mashup:create')
        ->expectsQuestion('Select a device', $device->id)
        ->expectsQuestion('Select a playlist', $playlist->id)
        ->expectsQuestion('Select a layout', '1Lx1R')
        ->expectsQuestion('Enter a name for this mashup', 'Mashup') // Default value
        ->expectsQuestion('Select the first plugin', $plugin1->id)
        ->expectsQuestion('Select the second plugin', $plugin2->id)
        ->expectsOutput('Mashup created successfully!')
        ->assertExitCode(0);

    $playlistItem = PlaylistItem::where('playlist_id', $playlist->id)
        ->whereJsonContains('mashup->mashup_name', 'Mashup')
        ->first();

    expect($playlistItem)->not->toBeNull();
});

test('mashup create command handles 1x1 layout with single plugin', function (): void {
    $user = User::factory()->create();
    $device = Device::factory()->create(['user_id' => $user->id]);
    $playlist = Playlist::factory()->create(['device_id' => $device->id]);
    $plugin = Plugin::factory()->create(['user_id' => $user->id]);

    $this->artisan('mashup:create')
        ->expectsQuestion('Select a device', $device->id)
        ->expectsQuestion('Select a playlist', $playlist->id)
        ->expectsQuestion('Select a layout', '1x1')
        ->expectsQuestion('Enter a name for this mashup', 'Single Plugin Mashup')
        ->expectsQuestion('Select the first plugin', $plugin->id)
        ->expectsOutput('Mashup created successfully!')
        ->assertExitCode(0);

    $playlistItem = PlaylistItem::where('playlist_id', $playlist->id)
        ->whereJsonContains('mashup->mashup_name', 'Single Plugin Mashup')
        ->first();

    expect($playlistItem)->not->toBeNull();
    expect($playlistItem->getMashupLayoutType())->toBe('1x1');
    expect($playlistItem->getMashupPluginIds())->toHaveCount(1);
    expect($playlistItem->getMashupPluginIds())->toContain($plugin->id);
});
