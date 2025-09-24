<?php

declare(strict_types=1);

use App\Notifications\Messages\WebhookMessage;

test('webhook message can be created with static method', function (): void {
    $message = WebhookMessage::create('test data');

    expect($message)->toBeInstanceOf(WebhookMessage::class);
});

test('webhook message can be created with constructor', function (): void {
    $message = new WebhookMessage('test data');

    expect($message)->toBeInstanceOf(WebhookMessage::class);
});

test('webhook message can set query parameters', function (): void {
    $message = WebhookMessage::create()
        ->query(['param1' => 'value1', 'param2' => 'value2']);

    expect($message->toArray()['query'])->toBe(['param1' => 'value1', 'param2' => 'value2']);
});

test('webhook message can set data', function (): void {
    $data = ['key' => 'value', 'nested' => ['array' => 'data']];
    $message = WebhookMessage::create()
        ->data($data);

    expect($message->toArray()['data'])->toBe($data);
});

test('webhook message can add headers', function (): void {
    $message = WebhookMessage::create()
        ->header('X-Custom-Header', 'custom-value')
        ->header('Authorization', 'Bearer token');

    $headers = $message->toArray()['headers'];
    expect($headers['X-Custom-Header'])->toBe('custom-value');
    expect($headers['Authorization'])->toBe('Bearer token');
});

test('webhook message can set user agent', function (): void {
    $message = WebhookMessage::create()
        ->userAgent('Test App/1.0');

    $headers = $message->toArray()['headers'];
    expect($headers['User-Agent'])->toBe('Test App/1.0');
});

test('webhook message can set verify option', function (): void {
    $message = WebhookMessage::create()
        ->verify(true);

    expect($message->toArray()['verify'])->toBeTrue();
});

test('webhook message verify defaults to false', function (): void {
    $message = WebhookMessage::create();

    expect($message->toArray()['verify'])->toBeFalse();
});

test('webhook message can chain methods', function (): void {
    $message = WebhookMessage::create(['initial' => 'data'])
        ->query(['param' => 'value'])
        ->data(['updated' => 'data'])
        ->header('X-Test', 'header')
        ->userAgent('Test Agent')
        ->verify(true);

    $array = $message->toArray();

    expect($array['query'])->toBe(['param' => 'value']);
    expect($array['data'])->toBe(['updated' => 'data']);
    expect($array['headers']['X-Test'])->toBe('header');
    expect($array['headers']['User-Agent'])->toBe('Test Agent');
    expect($array['verify'])->toBeTrue();
});

test('webhook message toArray returns correct structure', function (): void {
    $message = WebhookMessage::create(['test' => 'data']);

    $array = $message->toArray();

    expect($array)->toHaveKeys(['query', 'data', 'headers', 'verify']);
    expect($array['query'])->toBeNull();
    expect($array['data'])->toBe(['test' => 'data']);
    expect($array['headers'])->toBeNull();
    expect($array['verify'])->toBeFalse();
});
