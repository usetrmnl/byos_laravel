<?php

declare(strict_types=1);

use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('allows an authenticated user to fetch device models', function (): void {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/device-models');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'label',
                    'description',
                    'width',
                    'height',
                    'bit_depth',
                ],
            ],
        ]);
});

it('blocks unauthenticated users from accessing device models', function (): void {
    $response = $this->getJson('/api/device-models');

    $response->assertUnauthorized();
});
