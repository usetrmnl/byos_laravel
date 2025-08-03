<?php

namespace App\Services;

use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\ProviderInterface;
use Laravel\Socialite\Two\User;
use GuzzleHttp\Client;
use Illuminate\Support\Arr;

class OidcProvider extends AbstractProvider implements ProviderInterface
{
    /**
     * The scopes being requested.
     */
    protected $scopes = [];

    /**
     * The separating character for the requested scopes.
     */
    protected $scopeSeparator = ' ';

    /**
     * The OIDC configuration.
     */
    protected $oidcConfig;

    /**
     * The base URL for OIDC endpoints.
     */
    protected $baseUrl;

    /**
     * Create a new provider instance.
     */
    public function __construct($request, $clientId, $clientSecret, $redirectUrl, $scopes = [], $guzzle = [])
    {
        parent::__construct($request, $clientId, $clientSecret, $redirectUrl, $guzzle);
        
        $endpoint = config('services.oidc.endpoint');
        if (!$endpoint) {
            throw new \Exception('OIDC endpoint is not configured. Please set OIDC_ENDPOINT environment variable.');
        }
        
        // Handle both full well-known URL and base URL
        if (str_ends_with($endpoint, '/.well-known/openid-configuration')) {
            $this->baseUrl = str_replace('/.well-known/openid-configuration', '', $endpoint);
        } else {
            $this->baseUrl = rtrim($endpoint, '/');
        }
        
        $this->scopes = $scopes ?: ['openid', 'profile', 'email'];
        $this->loadOidcConfiguration();
    }

    /**
     * Load OIDC configuration from the well-known endpoint.
     */
    protected function loadOidcConfiguration()
    {
        try {
            $url = $this->baseUrl . '/.well-known/openid-configuration';
            $client = new Client();
            $response = $client->get($url);
            $this->oidcConfig = json_decode($response->getBody()->getContents(), true);
            
            if (!$this->oidcConfig) {
                throw new \Exception('OIDC configuration is empty or invalid JSON');
            }
            
            if (!isset($this->oidcConfig['authorization_endpoint'])) {
                throw new \Exception('authorization_endpoint not found in OIDC configuration');
            }
            
        } catch (\Exception $e) {
            throw new \Exception('Failed to load OIDC configuration: ' . $e->getMessage());
        }
    }

    /**
     * Get the authentication URL for the provider.
     */
    protected function getAuthUrl($state)
    {
        if (!$this->oidcConfig || !isset($this->oidcConfig['authorization_endpoint'])) {
            throw new \Exception('OIDC configuration not loaded or authorization_endpoint not found.');
        }
        return $this->buildAuthUrlFromBase($this->oidcConfig['authorization_endpoint'], $state);
    }

    /**
     * Get the token URL for the provider.
     */
    protected function getTokenUrl()
    {
        if (!$this->oidcConfig || !isset($this->oidcConfig['token_endpoint'])) {
            throw new \Exception('OIDC configuration not loaded or token_endpoint not found.');
        }
        return $this->oidcConfig['token_endpoint'];
    }

    /**
     * Get the raw user for the given access token.
     */
    protected function getUserByToken($token)
    {
        if (!$this->oidcConfig || !isset($this->oidcConfig['userinfo_endpoint'])) {
            throw new \Exception('OIDC configuration not loaded or userinfo_endpoint not found.');
        }
        
        $response = $this->getHttpClient()->get($this->oidcConfig['userinfo_endpoint'], [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
            ],
        ]);

        return json_decode($response->getBody(), true);
    }

    /**
     * Map the raw user array to a Socialite User instance.
     */
    protected function mapUserToObject(array $user)
    {
        return (new User)->setRaw($user)->map([
            'id' => $user['sub'],
            'nickname' => $user['preferred_username'] ?? null,
            'name' => $user['name'] ?? null,
            'email' => $user['email'] ?? null,
            'avatar' => $user['picture'] ?? null,
        ]);
    }

    /**
     * Get the access token response for the given code.
     */
    public function getAccessTokenResponse($code)
    {
        $response = $this->getHttpClient()->post($this->getTokenUrl(), [
            'headers' => ['Accept' => 'application/json'],
            'form_params' => $this->getTokenFields($code),
        ]);

        return json_decode($response->getBody(), true);
    }

    /**
     * Get the POST fields for the token request.
     */
    protected function getTokenFields($code)
    {
        return array_merge(parent::getTokenFields($code), [
            'grant_type' => 'authorization_code',
        ]);
    }
}