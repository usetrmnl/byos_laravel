<?php

namespace App\Providers;

use App\Services\OidcProvider;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Socialite\Facades\Socialite;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (app()->isProduction() && config('app.force_https')) {
            URL::forceScheme('https');
        }

        // Register OIDC provider with Socialite
        Socialite::extend('oidc', function ($app) {
            $config = $app['config']['services.oidc'] ?? [];
            return new OidcProvider(
                $app['request'],
                $config['client_id'] ?? null,
                $config['client_secret'] ?? null,
                $config['redirect'] ?? null,
                $config['scopes'] ?? ['openid', 'profile', 'email']
            );
        });
    }
}
