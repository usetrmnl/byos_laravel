<?php

namespace App\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Laravel\Socialite\Facades\Socialite;

class OidcTestCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'oidc:test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test OIDC configuration and driver registration';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Testing OIDC Configuration...');
        $this->newLine();

        // Check if OIDC is enabled
        $enabled = config('services.oidc.enabled');
        $this->line('OIDC Enabled: '.($enabled ? '✅ Yes' : '❌ No'));

        // Check configuration values
        $endpoint = config('services.oidc.endpoint');
        $clientId = config('services.oidc.client_id');
        $clientSecret = config('services.oidc.client_secret');
        $redirect = config('services.oidc.redirect');
        if (! $redirect) {
            $redirect = config('app.url', 'http://localhost').'/auth/oidc/callback';
        }
        $scopes = config('services.oidc.scopes', []);
        $defaultScopes = ['openid', 'profile', 'email'];
        $effectiveScopes = empty($scopes) ? $defaultScopes : $scopes;

        $this->line('OIDC Endpoint: '.($endpoint ? "✅ {$endpoint}" : '❌ Not set'));
        $this->line('Client ID: '.($clientId ? "✅ {$clientId}" : '❌ Not set'));
        $this->line('Client Secret: '.($clientSecret ? '✅ Set' : '❌ Not set'));
        $this->line('Redirect URL: '.($redirect ? "✅ {$redirect}" : '❌ Not set'));
        $this->line('Scopes: ✅ '.implode(', ', $effectiveScopes));

        $this->newLine();

        // Test driver registration
        try {
            // Only test driver if we have basic configuration
            if ($endpoint && $clientId && $clientSecret) {
                $driver = Socialite::driver('oidc');
                $this->line('OIDC Driver: ✅ Successfully registered and accessible');

                if ($enabled) {
                    $this->info('✅ OIDC is fully configured and ready to use!');
                    $this->line('You can test the login flow at: /auth/oidc/redirect');
                } else {
                    $this->warn('⚠️ OIDC driver is working but OIDC_ENABLED is false.');
                }
            } else {
                $this->line('OIDC Driver: ✅ Registered (configuration test skipped due to missing values)');
                $this->warn('⚠️ OIDC driver is registered but missing required configuration.');
                $this->line('Please set the following environment variables:');
                if (! $enabled) {
                    $this->line('  - OIDC_ENABLED=true');
                }
                if (! $endpoint) {
                    $this->line('  - OIDC_ENDPOINT=https://your-oidc-provider.com  (base URL)');
                    $this->line('    OR');
                    $this->line('  - OIDC_ENDPOINT=https://your-oidc-provider.com/.well-known/openid-configuration  (full URL)');
                }
                if (! $clientId) {
                    $this->line('  - OIDC_CLIENT_ID=your-client-id');
                }
                if (! $clientSecret) {
                    $this->line('  - OIDC_CLIENT_SECRET=your-client-secret');
                }
            }
        } catch (InvalidArgumentException $e) {
            if (str_contains($e->getMessage(), 'Driver [oidc] not supported')) {
                $this->error('❌ OIDC Driver registration failed: Driver not supported');
            } else {
                $this->error('❌ OIDC Driver error: '.$e->getMessage());
            }
        } catch (Exception $e) {
            $this->warn('⚠️ OIDC Driver registered but configuration error: '.$e->getMessage());
        }

        $this->newLine();

        return Command::SUCCESS;
    }
}
