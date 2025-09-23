<?php

declare(strict_types=1);

use App\Services\OidcProvider;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Request;
use Laravel\Socialite\Two\User;

test('oidc provider throws exception when endpoint is not configured', function () {
    config(['services.oidc.endpoint' => null]);

    expect(fn () => new OidcProvider(
        new Request(),
        'client-id',
        'client-secret',
        'redirect-url'
    ))->toThrow(Exception::class, 'OIDC endpoint is not configured');
});

test('oidc provider handles well-known endpoint url', function () {
    config(['services.oidc.endpoint' => 'https://example.com/.well-known/openid-configuration']);

    $mockClient = Mockery::mock(Client::class);
    $mockResponse = Mockery::mock(Response::class);
    $mockResponse->shouldReceive('getBody->getContents')
        ->andReturn(json_encode([
            'authorization_endpoint' => 'https://example.com/auth',
            'token_endpoint' => 'https://example.com/token',
            'userinfo_endpoint' => 'https://example.com/userinfo',
        ]));

    $mockClient->shouldReceive('get')
        ->with('https://example.com/.well-known/openid-configuration')
        ->andReturn($mockResponse);

    $this->app->instance(Client::class, $mockClient);

    $provider = new OidcProvider(
        new Request(),
        'client-id',
        'client-secret',
        'redirect-url'
    );

    expect($provider)->toBeInstanceOf(OidcProvider::class);
});

test('oidc provider handles base url endpoint', function () {
    config(['services.oidc.endpoint' => 'https://example.com']);

    $mockClient = Mockery::mock(Client::class);
    $mockResponse = Mockery::mock(Response::class);
    $mockResponse->shouldReceive('getBody->getContents')
        ->andReturn(json_encode([
            'authorization_endpoint' => 'https://example.com/auth',
            'token_endpoint' => 'https://example.com/token',
            'userinfo_endpoint' => 'https://example.com/userinfo',
        ]));

    $mockClient->shouldReceive('get')
        ->with('https://example.com/.well-known/openid-configuration')
        ->andReturn($mockResponse);

    $this->app->instance(Client::class, $mockClient);

    $provider = new OidcProvider(
        new Request(),
        'client-id',
        'client-secret',
        'redirect-url'
    );

    expect($provider)->toBeInstanceOf(OidcProvider::class);
});

test('oidc provider throws exception when configuration is empty', function () {
    config(['services.oidc.endpoint' => 'https://example.com']);

    $mockClient = Mockery::mock(Client::class);
    $mockResponse = Mockery::mock(Response::class);
    $mockResponse->shouldReceive('getBody->getContents')
        ->andReturn('');

    $mockClient->shouldReceive('get')
        ->with('https://example.com/.well-known/openid-configuration')
        ->andReturn($mockResponse);

    $this->app->instance(Client::class, $mockClient);

    expect(fn () => new OidcProvider(
        new Request(),
        'client-id',
        'client-secret',
        'redirect-url'
    ))->toThrow(Exception::class, 'OIDC configuration is empty or invalid JSON');
});

test('oidc provider throws exception when authorization endpoint is missing', function () {
    config(['services.oidc.endpoint' => 'https://example.com']);

    $mockClient = Mockery::mock(Client::class);
    $mockResponse = Mockery::mock(Response::class);
    $mockResponse->shouldReceive('getBody->getContents')
        ->andReturn(json_encode([
            'token_endpoint' => 'https://example.com/token',
            'userinfo_endpoint' => 'https://example.com/userinfo',
        ]));

    $mockClient->shouldReceive('get')
        ->with('https://example.com/.well-known/openid-configuration')
        ->andReturn($mockResponse);

    $this->app->instance(Client::class, $mockClient);

    expect(fn () => new OidcProvider(
        new Request(),
        'client-id',
        'client-secret',
        'redirect-url'
    ))->toThrow(Exception::class, 'authorization_endpoint not found in OIDC configuration');
});

test('oidc provider throws exception when configuration request fails', function () {
    config(['services.oidc.endpoint' => 'https://example.com']);

    $mockClient = Mockery::mock(Client::class);
    $mockClient->shouldReceive('get')
        ->with('https://example.com/.well-known/openid-configuration')
        ->andThrow(new RequestException('Connection failed', new GuzzleHttp\Psr7\Request('GET', 'test')));

    $this->app->instance(Client::class, $mockClient);

    expect(fn () => new OidcProvider(
        new Request(),
        'client-id',
        'client-secret',
        'redirect-url'
    ))->toThrow(Exception::class, 'Failed to load OIDC configuration');
});

test('oidc provider uses default scopes when none provided', function () {
    config(['services.oidc.endpoint' => 'https://example.com']);

    $mockClient = Mockery::mock(Client::class);
    $mockResponse = Mockery::mock(Response::class);
    $mockResponse->shouldReceive('getBody->getContents')
        ->andReturn(json_encode([
            'authorization_endpoint' => 'https://example.com/auth',
            'token_endpoint' => 'https://example.com/token',
            'userinfo_endpoint' => 'https://example.com/userinfo',
        ]));

    $mockClient->shouldReceive('get')
        ->with('https://example.com/.well-known/openid-configuration')
        ->andReturn($mockResponse);

    $this->app->instance(Client::class, $mockClient);

    $provider = new OidcProvider(
        new Request(),
        'client-id',
        'client-secret',
        'redirect-url'
    );

    expect($provider)->toBeInstanceOf(OidcProvider::class);
});

test('oidc provider uses custom scopes when provided', function () {
    config(['services.oidc.endpoint' => 'https://example.com']);

    $mockClient = Mockery::mock(Client::class);
    $mockResponse = Mockery::mock(Response::class);
    $mockResponse->shouldReceive('getBody->getContents')
        ->andReturn(json_encode([
            'authorization_endpoint' => 'https://example.com/auth',
            'token_endpoint' => 'https://example.com/token',
            'userinfo_endpoint' => 'https://example.com/userinfo',
        ]));

    $mockClient->shouldReceive('get')
        ->with('https://example.com/.well-known/openid-configuration')
        ->andReturn($mockResponse);

    $this->app->instance(Client::class, $mockClient);

    $provider = new OidcProvider(
        new Request(),
        'client-id',
        'client-secret',
        'redirect-url',
        ['openid', 'profile', 'email', 'custom_scope']
    );

    expect($provider)->toBeInstanceOf(OidcProvider::class);
});

test('oidc provider maps user data correctly', function () {
    config(['services.oidc.endpoint' => 'https://example.com']);

    $mockClient = Mockery::mock(Client::class);
    $mockResponse = Mockery::mock(Response::class);
    $mockResponse->shouldReceive('getBody->getContents')
        ->andReturn(json_encode([
            'authorization_endpoint' => 'https://example.com/auth',
            'token_endpoint' => 'https://example.com/token',
            'userinfo_endpoint' => 'https://example.com/userinfo',
        ]));

    $mockClient->shouldReceive('get')
        ->with('https://example.com/.well-known/openid-configuration')
        ->andReturn($mockResponse);

    $this->app->instance(Client::class, $mockClient);

    $provider = new OidcProvider(
        new Request(),
        'client-id',
        'client-secret',
        'redirect-url'
    );

    $userData = [
        'sub' => 'user123',
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'preferred_username' => 'johndoe',
        'picture' => 'https://example.com/avatar.jpg',
    ];

    $user = $provider->mapUserToObject($userData);

    expect($user)->toBeInstanceOf(User::class);
    expect($user->getId())->toBe('user123');
    expect($user->getName())->toBe('John Doe');
    expect($user->getEmail())->toBe('john@example.com');
    expect($user->getNickname())->toBe('johndoe');
    expect($user->getAvatar())->toBe('https://example.com/avatar.jpg');
});

test('oidc provider handles missing user fields gracefully', function () {
    config(['services.oidc.endpoint' => 'https://example.com']);

    $mockClient = Mockery::mock(Client::class);
    $mockResponse = Mockery::mock(Response::class);
    $mockResponse->shouldReceive('getBody->getContents')
        ->andReturn(json_encode([
            'authorization_endpoint' => 'https://example.com/auth',
            'token_endpoint' => 'https://example.com/token',
            'userinfo_endpoint' => 'https://example.com/userinfo',
        ]));

    $mockClient->shouldReceive('get')
        ->with('https://example.com/.well-known/openid-configuration')
        ->andReturn($mockResponse);

    $this->app->instance(Client::class, $mockClient);

    $provider = new OidcProvider(
        new Request(),
        'client-id',
        'client-secret',
        'redirect-url'
    );

    $userData = [
        'sub' => 'user123',
    ];

    $user = $provider->mapUserToObject($userData);

    expect($user)->toBeInstanceOf(User::class);
    expect($user->getId())->toBe('user123');
    expect($user->getName())->toBeNull();
    expect($user->getEmail())->toBeNull();
    expect($user->getNickname())->toBeNull();
    expect($user->getAvatar())->toBeNull();
});
