<?php

use App\Models\User;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

test('confirm password screen can be rendered', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('password.confirm'));

    $response->assertOk();
});
