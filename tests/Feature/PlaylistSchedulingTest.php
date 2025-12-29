<?php

use App\Models\Device;
use App\Models\Playlist;
use App\Models\PlaylistItem;
use App\Models\Plugin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

test('playlist scheduling works correctly for time ranges spanning midnight', function (): void {
    // Create a user and device
    $user = User::factory()->create();
    $device = Device::factory()->create(['user_id' => $user->id]);

    // Create two playlists with overlapping time ranges spanning midnight
    $playlist1 = Playlist::factory()->create([
        'device_id' => $device->id,
        'name' => 'Day until Deep Night Playlist',
        'is_active' => true,
        'active_from' => '09:01',
        'active_until' => '03:58',
        'weekdays' => null, // Active every day
    ]);

    $playlist2 = Playlist::factory()->create([
        'device_id' => $device->id,
        'name' => 'Early Morning Playlist',
        'is_active' => true,
        'active_from' => '04:00',
        'active_until' => '09:00',
        'weekdays' => null, // Active every day
    ]);

    // Create plugins and playlist items
    $plugin1 = Plugin::factory()->create(['name' => 'Day & Deep Night Plugin']);
    $plugin2 = Plugin::factory()->create(['name' => 'Morning Plugin']);

    PlaylistItem::factory()->create([
        'playlist_id' => $playlist1->id,
        'plugin_id' => $plugin1->id,
        'order' => 1,
        'is_active' => true,
    ]);

    PlaylistItem::factory()->create([
        'playlist_id' => $playlist2->id,
        'plugin_id' => $plugin2->id,
        'order' => 1,
        'is_active' => true,
    ]);

    // Test at 10:00 AM - should get playlist2 (Early Morning Playlist)
    Carbon::setTestNow(Carbon::create(2024, 1, 1, 4, 0, 0));

    $nextItem = $device->getNextPlaylistItem();
    expect($nextItem)->not->toBeNull();
    expect($nextItem->plugin->name)->toBe('Morning Plugin');
    expect($nextItem->playlist->name)->toBe('Early Morning Playlist');

    // Test at 2:00 AM - should get playlist1 (Day until Deep Night Playlist)
    Carbon::setTestNow(Carbon::create(2024, 1, 1, 10, 0, 0));

    $nextItem = $device->getNextPlaylistItem();
    expect($nextItem)->not->toBeNull();
    expect($nextItem->plugin->name)->toBe('Day & Deep Night Plugin');
    expect($nextItem->playlist->name)->toBe('Day until Deep Night Playlist');

    // Test at 5:00 AM - should get playlist2 (Early Morning Playlist)
    Carbon::setTestNow(Carbon::create(2024, 1, 1, 8, 0, 0));

    $nextItem = $device->getNextPlaylistItem();
    expect($nextItem)->not->toBeNull();
    expect($nextItem->plugin->name)->toBe('Morning Plugin');
    expect($nextItem->playlist->name)->toBe('Early Morning Playlist');

    // Test at 11:00 PM - should get playlist1 (Day until Deep Night Playlist)
    Carbon::setTestNow(Carbon::create(2024, 1, 1, 23, 0, 0));

    $nextItem = $device->getNextPlaylistItem();
    expect($nextItem)->not->toBeNull();
    expect($nextItem->plugin->name)->toBe('Day & Deep Night Plugin');
    expect($nextItem->playlist->name)->toBe('Day until Deep Night Playlist');
});

test('playlist isActiveNow handles midnight spanning correctly', function (): void {
    $playlist = Playlist::factory()->create([
        'is_active' => true,
        'active_from' => '09:01',
        'active_until' => '03:58',
        'weekdays' => null,
    ]);

    // Test at 2:00 AM - should be active
    Carbon::setTestNow(Carbon::create(2024, 1, 1, 2, 0, 0));
    expect($playlist->isActiveNow())->toBeTrue();

    // Test at 10:00 AM - should be active
    Carbon::setTestNow(Carbon::create(2024, 1, 1, 10, 0, 0));
    expect($playlist->isActiveNow())->toBeTrue();

    // Test at 5:00 AM - should NOT be active (gap between playlists)
    Carbon::setTestNow(Carbon::create(2024, 1, 1, 5, 0, 0));
    expect($playlist->isActiveNow())->toBeFalse();

    // Test at 8:00 AM - should NOT be active (gap between playlists)
    Carbon::setTestNow(Carbon::create(2024, 1, 1, 8, 0, 0));
    expect($playlist->isActiveNow())->toBeFalse();
});

test('playlist isActiveNow handles normal time ranges correctly', function (): void {
    $playlist = Playlist::factory()->create([
        'is_active' => true,
        'active_from' => '09:00',
        'active_until' => '17:00',
        'weekdays' => null,
    ]);

    // Test at 10:00 AM - should be active
    Carbon::setTestNow(Carbon::create(2024, 1, 1, 10, 0, 0));
    expect($playlist->isActiveNow())->toBeTrue();

    // Test at 2:00 AM - should NOT be active
    Carbon::setTestNow(Carbon::create(2024, 1, 1, 2, 0, 0));
    expect($playlist->isActiveNow())->toBeFalse();

    // Test at 8:00 PM - should NOT be active
    Carbon::setTestNow(Carbon::create(2024, 1, 1, 20, 0, 0));
    expect($playlist->isActiveNow())->toBeFalse();
});

test('playlist scheduling respects user timezone preference', function (): void {
    // Create a user with a timezone that's UTC+1 (e.g., Europe/Berlin)
    // This simulates the bug where setting 00:15 doesn't work until one hour later
    $user = User::factory()->create([
        'timezone' => 'Europe/Berlin', // UTC+1 in winter, UTC+2 in summer
    ]);

    $device = Device::factory()->create(['user_id' => $user->id]);

    // Create a playlist that should be active from 00:15 to 01:00 in the user's timezone
    $playlist = Playlist::factory()->create([
        'device_id' => $device->id,
        'is_active' => true,
        'active_from' => '00:15',
        'active_until' => '01:00',
        'weekdays' => null,
    ]);

    // Set test time to 00:15 in the user's timezone (Europe/Berlin)
    // In January, Europe/Berlin is UTC+1, so 00:15 Berlin time = 23:15 UTC the previous day
    // But Carbon::setTestNow uses UTC by default, so we need to set it to the UTC equivalent
    // For January 1, 2024 at 00:15 Berlin time (UTC+1), that's December 31, 2023 at 23:15 UTC
    $berlinTime = Carbon::create(2024, 1, 1, 0, 15, 0, 'Europe/Berlin');
    Carbon::setTestNow($berlinTime->utc());

    // The playlist should be active at 00:15 in the user's timezone
    // This test should pass after the fix, but will fail with the current bug
    expect($playlist->isActiveNow())->toBeTrue();

    // Test at 00:30 in user's timezone - should still be active
    $berlinTime = Carbon::create(2024, 1, 1, 0, 30, 0, 'Europe/Berlin');
    Carbon::setTestNow($berlinTime->utc());
    expect($playlist->isActiveNow())->toBeTrue();

    // Test at 01:15 in user's timezone - should NOT be active (past the end time)
    $berlinTime = Carbon::create(2024, 1, 1, 1, 15, 0, 'Europe/Berlin');
    Carbon::setTestNow($berlinTime->utc());
    expect($playlist->isActiveNow())->toBeFalse();

    // Test at 00:10 in user's timezone - should NOT be active (before start time)
    $berlinTime = Carbon::create(2024, 1, 1, 0, 10, 0, 'Europe/Berlin');
    Carbon::setTestNow($berlinTime->utc());
    expect($playlist->isActiveNow())->toBeFalse();
});
