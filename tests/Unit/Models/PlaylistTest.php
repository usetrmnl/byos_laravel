<?php

use App\Models\Device;
use App\Models\Playlist;
use App\Models\PlaylistItem;

test('playlist has required attributes', function (): void {
    $playlist = Playlist::factory()->create([
        'name' => 'Test Playlist',
        'is_active' => true,
        'weekdays' => [1, 2, 3],
        'active_from' => '09:00',
        'active_until' => '17:00',
    ]);

    expect($playlist)
        ->name->toBe('Test Playlist')
        ->is_active->toBeTrue()
        ->weekdays->toBe([1, 2, 3])
        ->active_from->format('H:i')->toBe('09:00')
        ->active_until->format('H:i')->toBe('17:00');
});

test('playlist belongs to device', function (): void {
    $device = Device::factory()->create();
    $playlist = Playlist::factory()->create(['device_id' => $device->id]);

    expect($playlist->device)
        ->toBeInstanceOf(Device::class)
        ->id->toBe($device->id);
});

test('playlist has many items', function (): void {
    $playlist = Playlist::factory()->create();
    $items = PlaylistItem::factory()->count(3)->create(['playlist_id' => $playlist->id]);

    expect($playlist->items)
        ->toHaveCount(3)
        ->each->toBeInstanceOf(PlaylistItem::class);
});

test('getNextPlaylistItem returns null when playlist is inactive', function (): void {
    $playlist = Playlist::factory()->create(['is_active' => false]);

    expect($playlist->getNextPlaylistItem())->toBeNull();
});
