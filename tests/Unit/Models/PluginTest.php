<?php

use App\Models\Plugin;
use Illuminate\Support\Facades\Http;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

test('plugin has required attributes', function () {
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

test('plugin automatically generates uuid on creation', function () {
    $plugin = Plugin::factory()->create();

    expect($plugin->uuid)
        ->toBeString()
        ->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/');
});

test('plugin can have custom uuid', function () {
    $uuid = Illuminate\Support\Str::uuid();
    $plugin = Plugin::factory()->create(['uuid' => $uuid]);

    expect($plugin->uuid)->toBe($uuid);
});

test('plugin data_payload is cast to array', function () {
    $data = ['key' => 'value'];
    $plugin = Plugin::factory()->create(['data_payload' => $data]);

    expect($plugin->data_payload)
        ->toBeArray()
        ->toBe($data);
});

test('plugin can have polling body for POST requests', function () {
    $plugin = Plugin::factory()->create([
        'polling_verb' => 'post',
        'polling_body' => '{"query": "query { user { id name } }"}',
    ]);

    expect($plugin->polling_body)->toBe('{"query": "query { user { id name } }"}');
});

test('updateDataPayload sends POST request with body when polling_verb is post', function () {
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

    Http::assertSent(function ($request) {
        return $request->url() === 'https://example.com/api' &&
               $request->method() === 'POST' &&
               $request->body() === '{"query": "query { user { id name } }"}';
    });
});
