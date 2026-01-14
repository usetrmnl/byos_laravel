<?php

declare(strict_types=1);

use App\Models\Plugin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('recipe settings can save trmnlp_id', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $plugin = Plugin::factory()->create([
        'user_id' => $user->id,
        'trmnlp_id' => null,
    ]);

    $trmnlpId = (string) Str::uuid();

    Livewire::test('plugins.recipes.settings', ['plugin' => $plugin])
        ->set('trmnlp_id', $trmnlpId)
        ->call('saveTrmnlpId')
        ->assertHasNoErrors();

    expect($plugin->fresh()->trmnlp_id)->toBe($trmnlpId);
});

test('recipe settings validates trmnlp_id is unique per user', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $existingPlugin = Plugin::factory()->create([
        'user_id' => $user->id,
        'trmnlp_id' => 'existing-id-123',
    ]);

    $newPlugin = Plugin::factory()->create([
        'user_id' => $user->id,
        'trmnlp_id' => null,
    ]);

    Livewire::test('plugins.recipes.settings', ['plugin' => $newPlugin])
        ->set('trmnlp_id', 'existing-id-123')
        ->call('saveTrmnlpId')
        ->assertHasErrors(['trmnlp_id' => 'unique']);

    expect($newPlugin->fresh()->trmnlp_id)->toBeNull();
});

test('recipe settings allows same trmnlp_id for different users', function (): void {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $plugin1 = Plugin::factory()->create([
        'user_id' => $user1->id,
        'trmnlp_id' => 'shared-id-123',
    ]);

    $plugin2 = Plugin::factory()->create([
        'user_id' => $user2->id,
        'trmnlp_id' => null,
    ]);

    $this->actingAs($user2);

    Livewire::test('plugins.recipes.settings', ['plugin' => $plugin2])
        ->set('trmnlp_id', 'shared-id-123')
        ->call('saveTrmnlpId')
        ->assertHasNoErrors();

    expect($plugin2->fresh()->trmnlp_id)->toBe('shared-id-123');
});

test('recipe settings allows same trmnlp_id for the same plugin', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $trmnlpId = (string) Str::uuid();

    $plugin = Plugin::factory()->create([
        'user_id' => $user->id,
        'trmnlp_id' => $trmnlpId,
    ]);

    Livewire::test('plugins.recipes.settings', ['plugin' => $plugin])
        ->set('trmnlp_id', $trmnlpId)
        ->call('saveTrmnlpId')
        ->assertHasNoErrors();

    expect($plugin->fresh()->trmnlp_id)->toBe($trmnlpId);
});

test('recipe settings can clear trmnlp_id', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $plugin = Plugin::factory()->create([
        'user_id' => $user->id,
        'trmnlp_id' => 'some-id',
    ]);

    Livewire::test('plugins.recipes.settings', ['plugin' => $plugin])
        ->set('trmnlp_id', '')
        ->call('saveTrmnlpId')
        ->assertHasNoErrors();

    expect($plugin->fresh()->trmnlp_id)->toBeNull();
});
