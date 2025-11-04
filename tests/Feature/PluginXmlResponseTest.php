<?php

declare(strict_types=1);

use App\Models\Plugin;
use Illuminate\Support\Facades\Http;

test('plugin parses JSON responses correctly', function (): void {
    Http::fake([
        'example.com/api/data' => Http::response([
            'title' => 'Test Data',
            'items' => [
                ['id' => 1, 'name' => 'Item 1'],
                ['id' => 2, 'name' => 'Item 2'],
            ],
        ], 200, ['Content-Type' => 'application/json']),
    ]);

    $plugin = Plugin::factory()->create([
        'data_strategy' => 'polling',
        'polling_url' => 'https://example.com/api/data',
        'polling_verb' => 'get',
    ]);

    $plugin->updateDataPayload();

    $plugin->refresh();

    expect($plugin->data_payload)->toBe([
        'title' => 'Test Data',
        'items' => [
            ['id' => 1, 'name' => 'Item 1'],
            ['id' => 2, 'name' => 'Item 2'],
        ],
    ]);
});

test('plugin parses XML responses and wraps under rss key', function (): void {
    $xmlContent = '<?xml version="1.0" encoding="UTF-8"?>
    <rss version="2.0">
        <channel>
            <title>Test RSS Feed</title>
            <item>
                <title>Test Item 1</title>
                <description>Description 1</description>
            </item>
            <item>
                <title>Test Item 2</title>
                <description>Description 2</description>
            </item>
        </channel>
    </rss>';

    Http::fake([
        'example.com/feed.xml' => Http::response($xmlContent, 200, ['Content-Type' => 'application/xml']),
    ]);

    $plugin = Plugin::factory()->create([
        'data_strategy' => 'polling',
        'polling_url' => 'https://example.com/feed.xml',
        'polling_verb' => 'get',
    ]);

    $plugin->updateDataPayload();

    $plugin->refresh();

    expect($plugin->data_payload)->toHaveKey('rss');
    expect($plugin->data_payload['rss'])->toHaveKey('@attributes');
    expect($plugin->data_payload['rss'])->toHaveKey('channel');
    expect($plugin->data_payload['rss']['channel']['title'])->toBe('Test RSS Feed');
    expect($plugin->data_payload['rss']['channel']['item'])->toHaveCount(2);
});

test('plugin parses JSON-parsable response body as JSON', function (): void {
    $jsonContent = '{"title": "Test Data", "items": [1, 2, 3]}';

    Http::fake([
        'example.com/data' => Http::response($jsonContent, 200, ['Content-Type' => 'text/plain']),
    ]);

    $plugin = Plugin::factory()->create([
        'data_strategy' => 'polling',
        'polling_url' => 'https://example.com/data',
        'polling_verb' => 'get',
    ]);

    $plugin->updateDataPayload();

    $plugin->refresh();

    expect($plugin->data_payload)->toBe([
        'title' => 'Test Data',
        'items' => [1, 2, 3],
    ]);
});

test('plugin wraps plain text response body as JSON', function (): void {
    $jsonContent = 'Lorem ipsum dolor sit amet';

    Http::fake([
        'example.com/data' => Http::response($jsonContent, 200, ['Content-Type' => 'text/plain']),
    ]);

    $plugin = Plugin::factory()->create([
        'data_strategy' => 'polling',
        'polling_url' => 'https://example.com/data',
        'polling_verb' => 'get',
    ]);

    $plugin->updateDataPayload();

    $plugin->refresh();

    expect($plugin->data_payload)->toBe([
        'data' => 'Lorem ipsum dolor sit amet',
    ]);
});

test('plugin handles invalid XML gracefully', function (): void {
    $invalidXml = '<root><item>unclosed tag';

    Http::fake([
        'example.com/invalid.xml' => Http::response($invalidXml, 200, ['Content-Type' => 'application/xml']),
    ]);

    $plugin = Plugin::factory()->create([
        'data_strategy' => 'polling',
        'polling_url' => 'https://example.com/invalid.xml',
        'polling_verb' => 'get',
    ]);

    $plugin->updateDataPayload();

    $plugin->refresh();

    expect($plugin->data_payload)->toBe(['error' => 'Failed to parse XML response']);
});

test('plugin handles multiple URLs with mixed content types', function (): void {
    $jsonResponse = ['title' => 'JSON Data', 'items' => [1, 2, 3]];
    $xmlContent = '<root><item>XML Data</item></root>';

    Http::fake([
        'example.com/json' => Http::response($jsonResponse, 200, ['Content-Type' => 'application/json']),
        'example.com/xml' => Http::response($xmlContent, 200, ['Content-Type' => 'application/xml']),
    ]);

    $plugin = Plugin::factory()->create([
        'data_strategy' => 'polling',
        'polling_url' => "https://example.com/json\nhttps://example.com/xml",
        'polling_verb' => 'get',
    ]);

    $plugin->updateDataPayload();

    $plugin->refresh();

    expect($plugin->data_payload)->toHaveKey('IDX_0');
    expect($plugin->data_payload)->toHaveKey('IDX_1');

    // First URL should be JSON
    expect($plugin->data_payload['IDX_0'])->toBe($jsonResponse);

    // Second URL should be XML wrapped under rss
    expect($plugin->data_payload['IDX_1'])->toHaveKey('rss');
    expect($plugin->data_payload['IDX_1']['rss']['item'])->toBe('XML Data');
});

test('plugin handles POST requests with XML responses', function (): void {
    $xmlContent = '<response><status>success</status><data>test</data></response>';

    Http::fake([
        'example.com/api' => Http::response($xmlContent, 200, ['Content-Type' => 'application/xml']),
    ]);

    $plugin = Plugin::factory()->create([
        'data_strategy' => 'polling',
        'polling_url' => 'https://example.com/api',
        'polling_verb' => 'post',
        'polling_body' => '{"query": "test"}',
    ]);

    $plugin->updateDataPayload();

    $plugin->refresh();

    expect($plugin->data_payload)->toHaveKey('rss');
    expect($plugin->data_payload['rss'])->toHaveKey('status');
    expect($plugin->data_payload['rss'])->toHaveKey('data');
    expect($plugin->data_payload['rss']['status'])->toBe('success');
    expect($plugin->data_payload['rss']['data'])->toBe('test');
});
