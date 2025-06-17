<?php

namespace App\Notifications\Channels;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Response;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Arr;

class WebhookChannel
{
    /** @var Client */
    protected $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * Send the given notification.
     *
     * @param  mixed  $notifiable
     *
     * @throws Exception
     * @throws GuzzleException
     */
    public function send($notifiable, Notification $notification): ?Response
    {
        $url = $notifiable->routeNotificationFor('webhook', $notification);

        if (! $url) {
            return null;
        }

        if (! method_exists($notification, 'toWebhook')) {
            throw new Exception('Notification does not implement toWebhook method.');
        }

        $webhookData = $notification->toWebhook($notifiable)->toArray();
        $response = $this->client->post($url, [
            'query' => Arr::get($webhookData, 'query'),
            'body' => json_encode(Arr::get($webhookData, 'data')),
            'verify' => Arr::get($webhookData, 'verify'),
            'headers' => Arr::get($webhookData, 'headers'),
        ]);

        if (! $response instanceof Response) {
            throw new Exception('Webhook request did not return a valid GuzzleHttp\Psr7\Response.');
        }

        if ($response->getStatusCode() >= 300 || $response->getStatusCode() < 200) {
            throw new Exception('Webhook request failed with status code: '.$response->getStatusCode());
        }

        return $response;
    }
}
