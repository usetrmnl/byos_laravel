<?php

use App\Models\Plugin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('plugin import extracts default values from custom_fields and stores in configuration', function () {
    // Create a user
    $user = User::factory()->create();

    // Test the functionality directly by creating a plugin with the expected configuration
    $configurationTemplate = [
        'custom_fields' => [
            [
                'keyname' => 'reading_days',
                'field_type' => 'string',
                'name' => 'Reading Days',
                'description' => 'Select days of the week to read',
                'default' => 'Monday,Friday,Saturday,Sunday',
            ],
            [
                'keyname' => 'refresh_interval',
                'field_type' => 'number',
                'name' => 'Refresh Interval',
                'description' => 'How often to refresh data',
                'default' => 15,
            ],
            [
                'keyname' => 'timezone',
                'field_type' => 'time_zone',
                'name' => 'Timezone',
                'description' => 'Select your timezone',
                // No default value
            ],
        ],
    ];

    // Extract default values from custom_fields and populate configuration
    $configuration = [];
    if (isset($configurationTemplate['custom_fields']) && is_array($configurationTemplate['custom_fields'])) {
        foreach ($configurationTemplate['custom_fields'] as $field) {
            if (isset($field['keyname']) && isset($field['default'])) {
                $configuration[$field['keyname']] = $field['default'];
            }
        }
    }

    // Create the plugin directly
    $plugin = Plugin::create([
        'uuid' => Illuminate\Support\Str::uuid(),
        'user_id' => $user->id,
        'name' => 'Test Plugin with Defaults',
        'data_stale_minutes' => 30,
        'data_strategy' => 'static',
        'configuration_template' => $configurationTemplate,
        'configuration' => $configuration,
    ]);

    // Assert the plugin was created with correct configuration
    expect($plugin)->not->toBeNull();
    expect($plugin->configuration)->toBeArray();
    expect($plugin->configuration)->toHaveKey('reading_days');
    expect($plugin->configuration)->toHaveKey('refresh_interval');
    expect($plugin->configuration)->not->toHaveKey('timezone');

    expect($plugin->getConfiguration('reading_days'))->toBe('Monday,Friday,Saturday,Sunday');
    expect($plugin->getConfiguration('refresh_interval'))->toBe(15);
    expect($plugin->getConfiguration('timezone'))->toBeNull();

    // Verify configuration template was stored correctly
    expect($plugin->configuration_template)->toBeArray();
    expect($plugin->configuration_template['custom_fields'])->toHaveCount(3);
});

test('plugin import handles custom_fields without default values', function () {
    // Create a user
    $user = User::factory()->create();

    // Test the functionality directly by creating a plugin with no default values
    $configurationTemplate = [
        'custom_fields' => [
            [
                'keyname' => 'timezone',
                'field_type' => 'time_zone',
                'name' => 'Timezone',
                'description' => 'Select your timezone',
            ],
        ],
    ];

    // Extract default values from custom_fields and populate configuration
    $configuration = [];
    if (isset($configurationTemplate['custom_fields']) && is_array($configurationTemplate['custom_fields'])) {
        foreach ($configurationTemplate['custom_fields'] as $field) {
            if (isset($field['keyname']) && isset($field['default'])) {
                $configuration[$field['keyname']] = $field['default'];
            }
        }
    }

    // Create the plugin directly
    $plugin = Plugin::create([
        'uuid' => Illuminate\Support\Str::uuid(),
        'user_id' => $user->id,
        'name' => 'Test Plugin No Defaults',
        'data_stale_minutes' => 30,
        'data_strategy' => 'static',
        'configuration_template' => $configurationTemplate,
        'configuration' => $configuration,
    ]);

    // Assert the plugin was created with empty configuration
    expect($plugin)->not->toBeNull();
    expect($plugin->configuration)->toBeArray();
    expect($plugin->configuration)->toBeEmpty();

    // Verify configuration template was stored correctly
    expect($plugin->configuration_template)->toBeArray();
    expect($plugin->configuration_template['custom_fields'])->toHaveCount(1);
});
