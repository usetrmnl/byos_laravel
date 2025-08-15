<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

test('configure view displays last_refreshed_at timestamp', function () {
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
