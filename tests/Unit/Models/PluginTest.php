<?php

use App\Models\Plugin;
use Illuminate\Support\Facades\Http;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

test('plugin has required attributes', function (): void {
    $plugin = Plugin::factory()->create([
        'name' => 'Test Plugin',
        'data_payload' => ['key' => 'value'],
    ]);

    expect($plugin)
        ->name->toBe('Test Plugin')
        ->data_payload->toBe(['key' => 'value'])
        ->uuid->toBeString()
        ->uuid->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/');
});

test('plugin automatically generates uuid on creation', function (): void {
    $plugin = Plugin::factory()->create();

    expect($plugin->uuid)
        ->toBeString()
        ->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/');
});

test('plugin can have custom uuid', function (): void {
    $uuid = Illuminate\Support\Str::uuid();
    $plugin = Plugin::factory()->create(['uuid' => $uuid]);

    expect($plugin->uuid)->toBe($uuid);
});

test('plugin data_payload is cast to array', function (): void {
    $data = ['key' => 'value'];
    $plugin = Plugin::factory()->create(['data_payload' => $data]);

    expect($plugin->data_payload)
        ->toBeArray()
        ->toBe($data);
});

test('plugin can have polling body for POST requests', function (): void {
    $plugin = Plugin::factory()->create([
        'polling_verb' => 'post',
        'polling_body' => '{"query": "query { user { id name } }"}',
    ]);

    expect($plugin->polling_body)->toBe('{"query": "query { user { id name } }"}');
});

test('updateDataPayload sends POST request with body when polling_verb is post', function (): void {
    Http::fake([
        'https://example.com/api' => Http::response(['success' => true], 200),
    ]);

    $plugin = Plugin::factory()->create([
        'data_strategy' => 'polling',
        'polling_url' => 'https://example.com/api',
        'polling_verb' => 'post',
        'polling_body' => '{"query": "query { user { id name } }"}',
    ]);

    $plugin->updateDataPayload();

    Http::assertSent(fn ($request): bool => $request->url() === 'https://example.com/api' &&
           $request->method() === 'POST' &&
           $request->body() === '{"query": "query { user { id name } }"}');
});

test('updateDataPayload handles multiple URLs with IDX_ prefixes', function (): void {
    $plugin = Plugin::factory()->create([
        'data_strategy' => 'polling',
        'polling_url' => "https://api1.example.com/data\nhttps://api2.example.com/weather\nhttps://api3.example.com/news",
        'polling_verb' => 'get',
        'configuration' => [
            'api_key' => 'test123',
        ],
    ]);

    // Mock HTTP responses
    Http::fake([
        'https://api1.example.com/data' => Http::response(['data' => 'test1'], 200),
        'https://api2.example.com/weather' => Http::response(['temp' => 25], 200),
        'https://api3.example.com/news' => Http::response(['headline' => 'test'], 200),
    ]);

    $plugin->updateDataPayload();

    expect($plugin->data_payload)->toHaveKey('IDX_0');
    expect($plugin->data_payload)->toHaveKey('IDX_1');
    expect($plugin->data_payload)->toHaveKey('IDX_2');
    expect($plugin->data_payload['IDX_0'])->toBe(['data' => 'test1']);
    expect($plugin->data_payload['IDX_1'])->toBe(['temp' => 25]);
    expect($plugin->data_payload['IDX_2'])->toBe(['headline' => 'test']);
});

test('updateDataPayload handles single URL without nesting', function (): void {
    $plugin = Plugin::factory()->create([
        'data_strategy' => 'polling',
        'polling_url' => 'https://api.example.com/data',
        'polling_verb' => 'get',
        'configuration' => [
            'api_key' => 'test123',
        ],
    ]);

    // Mock HTTP response
    Http::fake([
        'https://api.example.com/data' => Http::response(['data' => 'test'], 200),
    ]);

    $plugin->updateDataPayload();

    expect($plugin->data_payload)->toBe(['data' => 'test']);
    expect($plugin->data_payload)->not->toHaveKey('IDX_0');
});

test('updateDataPayload resolves Liquid variables in polling_header', function (): void {
    $plugin = Plugin::factory()->create([
        'data_strategy' => 'polling',
        'polling_url' => 'https://api.example.com/data',
        'polling_verb' => 'get',
        'polling_header' => "Authorization: Bearer {{ api_key }}\nX-Custom-Header: {{ custom_value }}",
        'configuration' => [
            'api_key' => 'test123',
            'custom_value' => 'custom_header_value',
        ],
    ]);

    // Mock HTTP response
    Http::fake([
        'https://api.example.com/data' => Http::response(['data' => 'test'], 200),
    ]);

    $plugin->updateDataPayload();

    Http::assertSent(fn ($request): bool => $request->url() === 'https://api.example.com/data' &&
           $request->method() === 'GET' &&
           $request->header('Authorization')[0] === 'Bearer test123' &&
           $request->header('X-Custom-Header')[0] === 'custom_header_value');
});

test('updateDataPayload resolves Liquid variables in polling_body', function (): void {
    $plugin = Plugin::factory()->create([
        'data_strategy' => 'polling',
        'polling_url' => 'https://api.example.com/data',
        'polling_verb' => 'post',
        'polling_body' => '{"query": "query { user { id name } }", "api_key": "{{ api_key }}", "user_id": "{{ user_id }}"}',
        'configuration' => [
            'api_key' => 'test123',
            'user_id' => '456',
        ],
    ]);

    // Mock HTTP response
    Http::fake([
        'https://api.example.com/data' => Http::response(['data' => 'test'], 200),
    ]);

    $plugin->updateDataPayload();

    Http::assertSent(function ($request): bool {
        $expectedBody = '{"query": "query { user { id name } }", "api_key": "test123", "user_id": "456"}';

        return $request->url() === 'https://api.example.com/data' &&
               $request->method() === 'POST' &&
               $request->body() === $expectedBody;
    });
});

test('webhook plugin is stale if webhook event occurred', function (): void {
    $plugin = Plugin::factory()->create([
        'data_strategy' => 'webhook',
        'data_payload_updated_at' => now()->subMinutes(10),
        'data_stale_minutes' => 60, // Should be ignored for webhook
    ]);

    expect($plugin->isDataStale())->toBeTrue();

});

test('webhook plugin data not stale if no webhook event occurred for 1 hour', function (): void {
    $plugin = Plugin::factory()->create([
        'data_strategy' => 'webhook',
        'data_payload_updated_at' => now()->subMinutes(60),
        'data_stale_minutes' => 60, // Should be ignored for webhook
    ]);

    expect($plugin->isDataStale())->toBeFalse();

});

test('plugin configuration is cast to array', function (): void {
    $config = ['timezone' => 'UTC', 'refresh_interval' => 30];
    $plugin = Plugin::factory()->create(['configuration' => $config]);

    expect($plugin->configuration)
        ->toBeArray()
        ->toBe($config);
});

test('plugin can get configuration value by key', function (): void {
    $config = ['timezone' => 'UTC', 'refresh_interval' => 30];
    $plugin = Plugin::factory()->create(['configuration' => $config]);

    expect($plugin->getConfiguration('timezone'))->toBe('UTC');
    expect($plugin->getConfiguration('refresh_interval'))->toBe(30);
    expect($plugin->getConfiguration('nonexistent', 'default'))->toBe('default');
});

test('plugin configuration template is cast to array', function (): void {
    $template = [
        'custom_fields' => [
            [
                'name' => 'Timezone',
                'keyname' => 'timezone',
                'field_type' => 'time_zone',
                'description' => 'Select your timezone',
            ],
        ],
    ];
    $plugin = Plugin::factory()->create(['configuration_template' => $template]);

    expect($plugin->configuration_template)
        ->toBeArray()
        ->toBe($template);
});

test('resolveLiquidVariables resolves variables from configuration', function (): void {
    $plugin = Plugin::factory()->create([
        'configuration' => [
            'api_key' => '12345',
            'username' => 'testuser',
            'count' => 42,
        ],
    ]);

    // Test simple variable replacement
    $template = 'API Key: {{ api_key }}';
    $result = $plugin->resolveLiquidVariables($template);
    expect($result)->toBe('API Key: 12345');

    // Test multiple variables
    $template = 'User: {{ username }}, Count: {{ count }}';
    $result = $plugin->resolveLiquidVariables($template);
    expect($result)->toBe('User: testuser, Count: 42');

    // Test with missing variable (should keep original)
    $template = 'Missing: {{ missing }}';
    $result = $plugin->resolveLiquidVariables($template);
    expect($result)->toBe('Missing: ');

    // Test with Liquid control structures
    $template = '{% if count > 40 %}High{% else %}Low{% endif %}';
    $result = $plugin->resolveLiquidVariables($template);
    expect($result)->toBe('High');
});

test('resolveLiquidVariables handles invalid Liquid syntax gracefully', function (): void {
    $plugin = Plugin::factory()->create([
        'configuration' => [
            'api_key' => '12345',
        ],
    ]);

    // Test with unclosed Liquid tag (should throw exception)
    $template = 'Unclosed tag: {{ config.api_key';

    expect(fn () => $plugin->resolveLiquidVariables($template))
        ->toThrow(Keepsuit\Liquid\Exceptions\SyntaxException::class);
});

test('plugin can extract default values from custom fields configuration template', function (): void {
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
                'default' => 30,
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

    $plugin = Plugin::factory()->create([
        'configuration_template' => $configurationTemplate,
        'configuration' => [
            'reading_days' => 'Monday,Friday,Saturday,Sunday',
            'refresh_interval' => 30,
        ],
    ]);

    expect($plugin->configuration)
        ->toBeArray()
        ->toHaveKey('reading_days')
        ->toHaveKey('refresh_interval')
        ->not->toHaveKey('timezone');

    expect($plugin->getConfiguration('reading_days'))->toBe('Monday,Friday,Saturday,Sunday');
    expect($plugin->getConfiguration('refresh_interval'))->toBe(30);
    expect($plugin->getConfiguration('timezone'))->toBeNull();
});

test('resolveLiquidVariables resolves configuration variables correctly', function (): void {
    $plugin = Plugin::factory()->create([
        'configuration' => [
            'Latitude' => '48.2083',
            'Longitude' => '16.3731',
            'api_key' => 'test123',
        ],
    ]);

    $template = 'https://suntracker.me/?lat={{ Latitude }}&lon={{ Longitude }}';
    $expected = 'https://suntracker.me/?lat=48.2083&lon=16.3731';

    expect($plugin->resolveLiquidVariables($template))->toBe($expected);
});

test('resolveLiquidVariables handles missing variables gracefully', function (): void {
    $plugin = Plugin::factory()->create([
        'configuration' => [
            'Latitude' => '48.2083',
        ],
    ]);

    $template = 'https://suntracker.me/?lat={{ Latitude }}&lon={{ Longitude }}&key={{ api_key }}';
    $expected = 'https://suntracker.me/?lat=48.2083&lon=&key=';

    expect($plugin->resolveLiquidVariables($template))->toBe($expected);
});

test('resolveLiquidVariables handles empty configuration', function (): void {
    $plugin = Plugin::factory()->create([
        'configuration' => [],
    ]);

    $template = 'https://suntracker.me/?lat={{ Latitude }}&lon={{ Longitude }}';
    $expected = 'https://suntracker.me/?lat=&lon=';

    expect($plugin->resolveLiquidVariables($template))->toBe($expected);
});
