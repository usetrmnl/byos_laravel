<?php

use App\Models\Plugin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('hasMissingRequiredConfigurationFields returns true when required field is null', function () {
    $user = User::factory()->create();

    $configurationTemplate = [
        'custom_fields' => [
            [
                'keyname' => 'api_key',
                'field_type' => 'string',
                'name' => 'API Key',
                'description' => 'Your API key',
                // Not marked as optional, so it's required
            ],
            [
                'keyname' => 'timezone',
                'field_type' => 'time_zone',
                'name' => 'Timezone',
                'description' => 'Select your timezone',
                'optional' => true, // Marked as optional
            ],
        ],
    ];

    $plugin = Plugin::factory()->create([
        'user_id' => $user->id,
        'configuration_template' => $configurationTemplate,
        'configuration' => [
            'timezone' => 'UTC', // Only timezone is set, api_key is missing
        ],
    ]);

    expect($plugin->hasMissingRequiredConfigurationFields())->toBeTrue();
});

test('hasMissingRequiredConfigurationFields returns false when all required fields are set', function () {
    $user = User::factory()->create();

    $configurationTemplate = [
        'custom_fields' => [
            [
                'keyname' => 'api_key',
                'field_type' => 'string',
                'name' => 'API Key',
                'description' => 'Your API key',
                // Not marked as optional, so it's required
            ],
            [
                'keyname' => 'timezone',
                'field_type' => 'time_zone',
                'name' => 'Timezone',
                'description' => 'Select your timezone',
                'optional' => true, // Marked as optional
            ],
        ],
    ];

    $plugin = Plugin::factory()->create([
        'user_id' => $user->id,
        'configuration_template' => $configurationTemplate,
        'configuration' => [
            'api_key' => 'test-api-key', // Required field is set
            'timezone' => 'UTC',
        ],
    ]);

    expect($plugin->hasMissingRequiredConfigurationFields())->toBeFalse();
});

test('hasMissingRequiredConfigurationFields returns false when no custom fields exist', function () {
    $user = User::factory()->create();

    $plugin = Plugin::factory()->create([
        'user_id' => $user->id,
        'configuration_template' => [],
        'configuration' => [],
    ]);

    expect($plugin->hasMissingRequiredConfigurationFields())->toBeFalse();
});

test('hasMissingRequiredConfigurationFields returns true when explicitly required field is null', function () {
    $user = User::factory()->create();

    $configurationTemplate = [
        'custom_fields' => [
            [
                'keyname' => 'api_key',
                'field_type' => 'string',
                'name' => 'API Key',
                'description' => 'Your API key',
                // Not marked as optional, so it's required
            ],
        ],
    ];

    $plugin = Plugin::factory()->create([
        'user_id' => $user->id,
        'configuration_template' => $configurationTemplate,
        'configuration' => [
            'api_key' => null, // Explicitly set to null
        ],
    ]);

    expect($plugin->hasMissingRequiredConfigurationFields())->toBeTrue();
});

test('hasMissingRequiredConfigurationFields returns true when required field is empty string', function () {
    $user = User::factory()->create();

    $configurationTemplate = [
        'custom_fields' => [
            [
                'keyname' => 'api_key',
                'field_type' => 'string',
                'name' => 'API Key',
                'description' => 'Your API key',
                // Not marked as optional, so it's required
            ],
        ],
    ];

    $plugin = Plugin::factory()->create([
        'user_id' => $user->id,
        'configuration_template' => $configurationTemplate,
        'configuration' => [
            'api_key' => '', // Empty string
        ],
    ]);

    expect($plugin->hasMissingRequiredConfigurationFields())->toBeTrue();
});

test('hasMissingRequiredConfigurationFields returns true when required array field is empty', function () {
    $user = User::factory()->create();

    $configurationTemplate = [
        'custom_fields' => [
            [
                'keyname' => 'selected_items',
                'field_type' => 'select',
                'name' => 'Selected Items',
                'description' => 'Select items',
                'multiple' => true,
                // Not marked as optional, so it's required
            ],
        ],
    ];

    $plugin = Plugin::factory()->create([
        'user_id' => $user->id,
        'configuration_template' => $configurationTemplate,
        'configuration' => [
            'selected_items' => [], // Empty array
        ],
    ]);

    expect($plugin->hasMissingRequiredConfigurationFields())->toBeTrue();
});

test('hasMissingRequiredConfigurationFields returns false when author_bio field is present but other required field is set', function () {
    $user = User::factory()->create();

    $configurationTemplate = [
        'custom_fields' => [
            [
                'keyname' => 'author_bio',
                'name' => 'About This Plugin',
                'field_type' => 'author_bio',
            ],
            [
                'keyname' => 'plugin_field',
                'name' => 'Field Name',
                'field_type' => 'string',
            ],
        ],
    ];

    $plugin = Plugin::factory()->create([
        'user_id' => $user->id,
        'configuration_template' => $configurationTemplate,
        'configuration' => [
            'plugin_field' => 'set', // Required field is set
        ],
    ]);

    expect($plugin->hasMissingRequiredConfigurationFields())->toBeFalse();
});

test('hasMissingRequiredConfigurationFields returns false when field has default value', function () {
    $user = User::factory()->create();

    $configurationTemplate = [
        'custom_fields' => [
            [
                'keyname' => 'api_key',
                'field_type' => 'string',
                'name' => 'API Key',
                'description' => 'Your API key',
                'default' => 'default-api-key', // Has default value
            ],
        ],
    ];

    $plugin = Plugin::factory()->create([
        'user_id' => $user->id,
        'configuration_template' => $configurationTemplate,
        'configuration' => [], // Empty configuration, but field has default
    ]);

    expect($plugin->hasMissingRequiredConfigurationFields())->toBeFalse();
});

test('hasMissingRequiredConfigurationFields returns true when required xhrSelect field is missing', function () {
    $user = User::factory()->create();

    $configurationTemplate = [
        'custom_fields' => [
            [
                'keyname' => 'team',
                'field_type' => 'xhrSelect',
                'name' => 'Baseball Team',
                'description' => 'Select your team',
                'endpoint' => 'https://usetrmnl.com/custom_plugin_example_xhr_select.json',
                // Not marked as optional, so it's required
            ],
        ],
    ];

    $plugin = Plugin::factory()->create([
        'user_id' => $user->id,
        'configuration_template' => $configurationTemplate,
        'configuration' => [], // Empty configuration
    ]);

    expect($plugin->hasMissingRequiredConfigurationFields())->toBeTrue();
});

test('hasMissingRequiredConfigurationFields returns false when required xhrSelect field is set', function () {
    $user = User::factory()->create();

    $configurationTemplate = [
        'custom_fields' => [
            [
                'keyname' => 'team',
                'field_type' => 'xhrSelect',
                'name' => 'Baseball Team',
                'description' => 'Select your team',
                'endpoint' => 'https://usetrmnl.com/custom_plugin_example_xhr_select.json',
                // Not marked as optional, so it's required
            ],
        ],
    ];

    $plugin = Plugin::factory()->create([
        'user_id' => $user->id,
        'configuration_template' => $configurationTemplate,
        'configuration' => [
            'team' => '123', // Required field is set
        ],
    ]);

    expect($plugin->hasMissingRequiredConfigurationFields())->toBeFalse();
});
