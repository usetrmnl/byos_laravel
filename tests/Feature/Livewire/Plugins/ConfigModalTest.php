<?php

use App\Models\Plugin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('config modal correctly loads multi_string defaults into UI boxes', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $plugin = Plugin::create([
        'uuid' => Str::uuid(),
        'user_id' => $user->id,
        'name' => 'Test Plugin',
        'data_strategy' => 'static',
        'configuration_template' => [
            'custom_fields' => [[
                'keyname' => 'tags',
                'field_type' => 'multi_string',
                'name' => 'Reading Days',
                'default' => 'alpha,beta',
            ]]
        ],
        'configuration' => ['tags' => 'alpha,beta']
    ]);

    Livewire::test('plugins.config-modal', ['plugin' => $plugin])
        ->assertSet('multiValues.tags', ['alpha', 'beta']);
});

test('config modal validates against commas in multi_string boxes', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $plugin = Plugin::create([
        'uuid' => Str::uuid(),
        'user_id' => $user->id,
        'name' => 'Test Plugin',
        'data_strategy' => 'static',
        'configuration_template' => [
            'custom_fields' => [[
                'keyname' => 'tags',
                'field_type' => 'multi_string',
                'name' => 'Reading Days',
            ]]
        ]
    ]);

    Livewire::test('plugins.config-modal', ['plugin' => $plugin])
        ->set('multiValues.tags.0', 'no,commas,allowed')
        ->call('saveConfiguration')
        ->assertHasErrors(['multiValues.tags.0' => 'regex']);

    // Assert DB remains unchanged
    expect($plugin->fresh()->configuration['tags'] ?? '')->not->toBe('no,commas,allowed');
});

test('config modal merges multi_string boxes into a single CSV string on save', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $plugin = Plugin::create([
        'uuid' => Str::uuid(),
        'user_id' => $user->id,
        'name' => 'Test Plugin',
        'data_strategy' => 'static',
        'configuration_template' => [
            'custom_fields' => [[
                'keyname' => 'items',
                'field_type' => 'multi_string',
                'name' => 'Reading Days',
            ]]
        ],
        'configuration' => []
    ]);

    Livewire::test('plugins.config-modal', ['plugin' => $plugin])
        ->set('multiValues.items.0', 'First')
        ->call('addMultiItem', 'items')
        ->set('multiValues.items.1', 'Second')
        ->call('saveConfiguration')
        ->assertHasNoErrors();

    expect($plugin->fresh()->configuration['items'])->toBe('First,Second');
});

test('config modal resetForm clears dirty state and increments resetIndex', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $plugin = Plugin::create([
        'uuid' => Str::uuid(),
        'user_id' => $user->id,
        'name' => 'Test Plugin',
        'data_strategy' => 'static',
        'configuration' => ['simple_key' => 'original_value']
    ]);

    Livewire::test('plugins.config-modal', ['plugin' => $plugin])
        ->set('configuration.simple_key', 'dirty_value')
        ->call('resetForm')
        ->assertSet('configuration.simple_key', 'original_value')
        ->assertSet('resetIndex', 1);
});

test('config modal dispatches update event for parent warning refresh', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $plugin = Plugin::create([
        'uuid' => Str::uuid(),
        'user_id' => $user->id,
        'name' => 'Test Plugin',
        'data_strategy' => 'static'
    ]);

    Livewire::test('plugins.config-modal', ['plugin' => $plugin])
        ->call('saveConfiguration')
        ->assertDispatched('config-updated');
});
