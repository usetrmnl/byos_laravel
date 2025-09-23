<?php

declare(strict_types=1);

use App\Models\Device;
use App\Models\User;
use App\Notifications\BatteryLow;
use App\Notifications\Channels\WebhookChannel;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Response;
use Illuminate\Notifications\Notification;

test('webhook channel returns null when no webhook url is configured', function () {
    $client = Mockery::mock(Client::class);
    $channel = new WebhookChannel($client);

    $user = new class extends User
    {
        public function routeNotificationFor($driver, $notification = null)
        {
            return null; // No webhook URL configured
        }
    };

    $notification = new BatteryLow(Device::factory()->create());

    $result = $channel->send($user, $notification);

    expect($result)->toBeNull();
});

test('webhook channel throws exception when notification does not implement toWebhook', function () {
    $client = Mockery::mock(Client::class);
    $channel = new WebhookChannel($client);

    $user = new class extends User
    {
        public function routeNotificationFor($driver, $notification = null)
        {
            return 'https://example.com/webhook';
        }
    };

    $notification = new class extends Notification
    {
        public function via($notifiable)
        {
            return [];
        }
    };

    expect(fn () => $channel->send($user, $notification))
        ->toThrow(Exception::class, 'Notification does not implement toWebhook method.');
});

test('webhook channel sends successful webhook request', function () {
    $client = Mockery::mock(Client::class);
    $channel = new WebhookChannel($client);

    $user = new class extends User
    {
        public function routeNotificationFor($driver, $notification = null)
        {
            return 'https://example.com/webhook';
        }
    };

    $device = Device::factory()->create();
    $notification = new BatteryLow($device);

    $expectedResponse = new Response(200, [], 'OK');

    $client->shouldReceive('post')
        ->once()
        ->with('https://example.com/webhook', [
            'query' => null,
            'body' => json_encode($notification->toWebhook($user)->toArray()['data']),
            'verify' => false,
            'headers' => $notification->toWebhook($user)->toArray()['headers'],
        ])
        ->andReturn($expectedResponse);

    $result = $channel->send($user, $notification);

    expect($result)->toBe($expectedResponse);
});

test('webhook channel throws exception when response status is not successful', function () {
    $client = Mockery::mock(Client::class);
    $channel = new WebhookChannel($client);

    $user = new class extends User
    {
        public function routeNotificationFor($driver, $notification = null)
        {
            return 'https://example.com/webhook';
        }
    };

    $device = Device::factory()->create();
    $notification = new BatteryLow($device);

    $errorResponse = new Response(400, [], 'Bad Request');

    $client->shouldReceive('post')
        ->once()
        ->andReturn($errorResponse);

    expect(fn () => $channel->send($user, $notification))
        ->toThrow(Exception::class, 'Webhook request failed with status code: 400');
});

test('webhook channel handles guzzle exceptions', function () {
    $client = Mockery::mock(Client::class);
    $channel = new WebhookChannel($client);

    $user = new class extends User
    {
        public function routeNotificationFor($driver, $notification = null)
        {
            return 'https://example.com/webhook';
        }
    };

    $device = Device::factory()->create();
    $notification = new BatteryLow($device);

    $client->shouldReceive('post')
        ->once()
        ->andThrow(new class extends Exception implements GuzzleException {});

    expect(fn () => $channel->send($user, $notification))
        ->toThrow(Exception::class);
});
