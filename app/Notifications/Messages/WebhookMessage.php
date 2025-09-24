<?php

namespace App\Notifications\Messages;

use Illuminate\Notifications\Notification;

final class WebhookMessage extends Notification
{
    /**
     * The GET parameters of the request.
     *
     * @var array|string|null
     */
    private $query;

    /**
     * The headers to send with the request.
     *
     * @var array|null
     */
    private $headers;

    /**
     * The Guzzle verify option.
     *
     * @var bool|string
     */
    private $verify = false;

    /**
     * @param  mixed  $data
     */
    public static function create($data = ''): self
    {
        return new self($data);
    }

    /**
     * @param  mixed  $data
     */
    public function __construct(
        /**
         * The POST data of the Webhook request.
         */
        private $data = ''
    ) {}

    /**
     * Set the Webhook parameters to be URL encoded.
     *
     * @param  mixed  $query
     * @return $this
     */
    public function query($query): self
    {
        $this->query = $query;

        return $this;
    }

    /**
     * Set the Webhook data to be JSON encoded.
     *
     * @param  mixed  $data
     * @return $this
     */
    public function data($data): self
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Add a Webhook request custom header.
     *
     * @param  string  $name
     * @param  string  $value
     * @return $this
     */
    public function header($name, $value): self
    {
        $this->headers[$name] = $value;

        return $this;
    }

    /**
     * Set the Webhook request UserAgent.
     *
     * @param  string  $userAgent
     * @return $this
     */
    public function userAgent($userAgent): self
    {
        $this->headers['User-Agent'] = $userAgent;

        return $this;
    }

    /**
     * Indicate that the request should be verified.
     *
     * @return $this
     */
    public function verify($value = true): self
    {
        $this->verify = $value;

        return $this;
    }

    public function toArray(): array
    {
        return [
            'query' => $this->query,
            'data' => $this->data,
            'headers' => $this->headers,
            'verify' => $this->verify,
        ];
    }
}
