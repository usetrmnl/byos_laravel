<?php

declare(strict_types=1);

use function Pest\Laravel\mock;

test('oidc test command has correct signature', function () {
    $this->artisan('oidc:test --help')
        ->assertExitCode(0);
});

test('oidc test command runs successfully with disabled oidc', function () {
    config([
        'app.url' => 'http://localhost',
        'services.oidc.enabled' => false,
        'services.oidc.endpoint' => null,
        'services.oidc.client_id' => null,
        'services.oidc.client_secret' => null,
        'services.oidc.redirect' => null,
        'services.oidc.scopes' => [],
    ]);

    $this->artisan('oidc:test')
        ->expectsOutput('Testing OIDC Configuration...')
        ->expectsOutput('OIDC Enabled: ❌ No')
        ->expectsOutput('OIDC Endpoint: ❌ Not set')
        ->expectsOutput('Client ID: ❌ Not set')
        ->expectsOutput('Client Secret: ❌ Not set')
        ->expectsOutput('Redirect URL: ✅ http://localhost/auth/oidc/callback')
        ->expectsOutput('Scopes: ✅ openid, profile, email')
        ->expectsOutput('OIDC Driver: ✅ Registered (configuration test skipped due to missing values)')
        ->expectsOutput('⚠️ OIDC driver is registered but missing required configuration.')
        ->expectsOutput('Please set the following environment variables:')
        ->expectsOutput('  - OIDC_ENABLED=true')
        ->expectsOutput('  - OIDC_ENDPOINT=https://your-oidc-provider.com  (base URL)')
        ->expectsOutput('    OR')
        ->expectsOutput('  - OIDC_ENDPOINT=https://your-oidc-provider.com/.well-known/openid-configuration  (full URL)')
        ->expectsOutput('  - OIDC_CLIENT_ID=your-client-id')
        ->expectsOutput('  - OIDC_CLIENT_SECRET=your-client-secret')
        ->assertExitCode(0);
});

test('oidc test command runs successfully with enabled oidc but missing config', function () {
    config([
        'app.url' => 'http://localhost',
        'services.oidc.enabled' => true,
        'services.oidc.endpoint' => null,
        'services.oidc.client_id' => null,
        'services.oidc.client_secret' => null,
        'services.oidc.redirect' => null,
        'services.oidc.scopes' => [],
    ]);

    $this->artisan('oidc:test')
        ->expectsOutput('Testing OIDC Configuration...')
        ->expectsOutput('OIDC Enabled: ✅ Yes')
        ->expectsOutput('OIDC Endpoint: ❌ Not set')
        ->expectsOutput('Client ID: ❌ Not set')
        ->expectsOutput('Client Secret: ❌ Not set')
        ->expectsOutput('Redirect URL: ✅ http://localhost/auth/oidc/callback')
        ->expectsOutput('Scopes: ✅ openid, profile, email')
        ->expectsOutput('OIDC Driver: ✅ Registered (configuration test skipped due to missing values)')
        ->expectsOutput('⚠️ OIDC driver is registered but missing required configuration.')
        ->expectsOutput('Please set the following environment variables:')
        ->expectsOutput('  - OIDC_ENDPOINT=https://your-oidc-provider.com  (base URL)')
        ->expectsOutput('    OR')
        ->expectsOutput('  - OIDC_ENDPOINT=https://your-oidc-provider.com/.well-known/openid-configuration  (full URL)')
        ->expectsOutput('  - OIDC_CLIENT_ID=your-client-id')
        ->expectsOutput('  - OIDC_CLIENT_SECRET=your-client-secret')
        ->assertExitCode(0);
});

test('oidc test command runs successfully with partial config', function () {
    config([
        'services.oidc.enabled' => true,
        'services.oidc.endpoint' => 'https://example.com',
        'services.oidc.client_id' => 'test-client-id',
        'services.oidc.client_secret' => null,
        'services.oidc.redirect' => 'https://example.com/callback',
        'services.oidc.scopes' => ['openid', 'profile'],
    ]);

    $this->artisan('oidc:test')
        ->expectsOutput('Testing OIDC Configuration...')
        ->expectsOutput('OIDC Enabled: ✅ Yes')
        ->expectsOutput('OIDC Endpoint: ✅ https://example.com')
        ->expectsOutput('Client ID: ✅ test-client-id')
        ->expectsOutput('Client Secret: ❌ Not set')
        ->expectsOutput('Redirect URL: ✅ https://example.com/callback')
        ->expectsOutput('Scopes: ✅ openid, profile')
        ->expectsOutput('OIDC Driver: ✅ Registered (configuration test skipped due to missing values)')
        ->expectsOutput('⚠️ OIDC driver is registered but missing required configuration.')
        ->expectsOutput('Please set the following environment variables:')
        ->expectsOutput('  - OIDC_CLIENT_SECRET=your-client-secret')
        ->assertExitCode(0);
});

test('oidc test command runs successfully with full config but disabled', function () {
    // Mock the HTTP client to return fake OIDC configuration
    mock(GuzzleHttp\Client::class, function ($mock) {
        $mock->shouldReceive('get')
            ->with('https://example.com/.well-known/openid-configuration')
            ->andReturn(new GuzzleHttp\Psr7\Response(200, [], json_encode([
                'authorization_endpoint' => 'https://example.com/auth',
                'token_endpoint' => 'https://example.com/token',
                'userinfo_endpoint' => 'https://example.com/userinfo',
            ])));
    });

    config([
        'services.oidc.enabled' => false,
        'services.oidc.endpoint' => 'https://example.com',
        'services.oidc.client_id' => 'test-client-id',
        'services.oidc.client_secret' => 'test-client-secret',
        'services.oidc.redirect' => 'https://example.com/callback',
        'services.oidc.scopes' => ['openid', 'profile'],
    ]);

    $this->artisan('oidc:test')
        ->expectsOutput('Testing OIDC Configuration...')
        ->expectsOutput('OIDC Enabled: ❌ No')
        ->expectsOutput('OIDC Endpoint: ✅ https://example.com')
        ->expectsOutput('Client ID: ✅ test-client-id')
        ->expectsOutput('Client Secret: ✅ Set')
        ->expectsOutput('Redirect URL: ✅ https://example.com/callback')
        ->expectsOutput('Scopes: ✅ openid, profile')
        ->expectsOutput('OIDC Driver: ✅ Successfully registered and accessible')
        ->expectsOutput('⚠️ OIDC driver is working but OIDC_ENABLED is false.')
        ->assertExitCode(0);
});

test('oidc test command runs successfully with full config and enabled', function () {
    // Mock the HTTP client to return fake OIDC configuration
    mock(GuzzleHttp\Client::class, function ($mock) {
        $mock->shouldReceive('get')
            ->with('https://example.com/.well-known/openid-configuration')
            ->andReturn(new GuzzleHttp\Psr7\Response(200, [], json_encode([
                'authorization_endpoint' => 'https://example.com/auth',
                'token_endpoint' => 'https://example.com/token',
                'userinfo_endpoint' => 'https://example.com/userinfo',
            ])));
    });

    config([
        'services.oidc.enabled' => true,
        'services.oidc.endpoint' => 'https://example.com',
        'services.oidc.client_id' => 'test-client-id',
        'services.oidc.client_secret' => 'test-client-secret',
        'services.oidc.redirect' => 'https://example.com/callback',
        'services.oidc.scopes' => ['openid', 'profile'],
    ]);

    $this->artisan('oidc:test')
        ->expectsOutput('Testing OIDC Configuration...')
        ->expectsOutput('OIDC Enabled: ✅ Yes')
        ->expectsOutput('OIDC Endpoint: ✅ https://example.com')
        ->expectsOutput('Client ID: ✅ test-client-id')
        ->expectsOutput('Client Secret: ✅ Set')
        ->expectsOutput('Redirect URL: ✅ https://example.com/callback')
        ->expectsOutput('Scopes: ✅ openid, profile')
        ->expectsOutput('OIDC Driver: ✅ Successfully registered and accessible')
        ->expectsOutput('✅ OIDC is fully configured and ready to use!')
        ->expectsOutput('You can test the login flow at: /auth/oidc/redirect')
        ->assertExitCode(0);
});

test('oidc test command handles empty scopes', function () {
    // Mock the HTTP client to return fake OIDC configuration
    mock(GuzzleHttp\Client::class, function ($mock) {
        $mock->shouldReceive('get')
            ->with('https://example.com/.well-known/openid-configuration')
            ->andReturn(new GuzzleHttp\Psr7\Response(200, [], json_encode([
                'authorization_endpoint' => 'https://example.com/auth',
                'token_endpoint' => 'https://example.com/token',
                'userinfo_endpoint' => 'https://example.com/userinfo',
            ])));
    });

    config([
        'services.oidc.enabled' => false,
        'services.oidc.endpoint' => 'https://example.com',
        'services.oidc.client_id' => 'test-client-id',
        'services.oidc.client_secret' => 'test-client-secret',
        'services.oidc.redirect' => 'https://example.com/callback',
        'services.oidc.scopes' => null,
    ]);

    $this->artisan('oidc:test')
        ->expectsOutput('Testing OIDC Configuration...')
        ->expectsOutput('OIDC Enabled: ❌ No')
        ->expectsOutput('OIDC Endpoint: ✅ https://example.com')
        ->expectsOutput('Client ID: ✅ test-client-id')
        ->expectsOutput('Client Secret: ✅ Set')
        ->expectsOutput('Redirect URL: ✅ https://example.com/callback')
        ->expectsOutput('Scopes: ✅ openid, profile, email')
        ->assertExitCode(0);
});
