<?php

namespace App\Notifications\Messages;

final class WebhookMessage
{
    /**
     * The GET parameters of the request.
     *
     * @var array|string|null
     */
    private $query;

    /**
     * The POST data of the Webhook request.
     *
     * @var mixed
     */
    private $data;

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
     * @return static
     */
    public static function create($data = '')
    {
        return new self($data);
    }

    /**
     * @param  mixed  $data
     */
    public function __construct($data = '')
    {
        $this->data = $data;
    }

    /**
     * Set the Webhook parameters to be URL encoded.
     *
     * @param  mixed  $query
     * @return $this
     */
    public function query($query)
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
    public function data($data)
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
    public function header($name, $value)
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
    public function userAgent($userAgent)
    {
        $this->headers['User-Agent'] = $userAgent;

        return $this;
    }

    /**
     * Indicate that the request should be verified.
     *
     * @return $this
     */
    public function verify($value = true)
    {
        $this->verify = $value;

        return $this;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return [
            'query' => $this->query,
            'data' => $this->data,
            'headers' => $this->headers,
            'verify' => $this->verify,
        ];
    }
}
