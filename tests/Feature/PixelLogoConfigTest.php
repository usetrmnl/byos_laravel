<?php

use App\Models\User;
use Illuminate\Support\Facades\Config;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

test('auth pages show pixel logo SVG when pixel_logo_enabled is true', function (): void {
    Config::set('app.pixel_logo_enabled', true);

    $response = $this->get('/login');

    $response->assertStatus(200);
    $response->assertSee('viewBox="0 0 1000 150"', false);
});

test('auth pages show heading instead of pixel logo when pixel_logo_enabled is false', function (): void {
    Config::set('app.pixel_logo_enabled', false);

    $response = $this->get('/login');

    $response->assertStatus(200);
    $response->assertDontSee('viewBox="0 0 1000 150"', false);
    $response->assertSee('LaraPaper');
});

test('app logo shows text when pixel_logo_enabled is false', function (): void {
    Config::set('app.pixel_logo_enabled', false);

    $user = User::factory()->create();
    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertStatus(200);
    $response->assertSee('LaraPaper');
    $response->assertDontSee('viewBox="0 0 1000 150"', false);
});

test('app logo shows pixel logo SVG when pixel_logo_enabled is true', function (): void {
    Config::set('app.pixel_logo_enabled', true);

    $user = User::factory()->create();
    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertStatus(200);
    $response->assertSee('viewBox="0 0 1000 150"', false);
});
