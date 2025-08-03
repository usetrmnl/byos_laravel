<?php

use App\Http\Controllers\Auth\OidcController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::middleware('guest')->group(function () {
    Volt::route('login', 'auth.login')
        ->name('login');

    if (config('app.registration.enabled')) {
        Volt::route('register', 'auth.register')
            ->name('register');
    }

    Volt::route('forgot-password', 'auth.forgot-password')
        ->name('password.request');

    Volt::route('reset-password/{token}', 'auth.reset-password')
        ->name('password.reset');

    // OIDC authentication routes
    Route::get('auth/oidc/redirect', [OidcController::class, 'redirect'])
        ->name('auth.oidc.redirect');

    Route::get('auth/oidc/callback', [OidcController::class, 'callback'])
        ->name('auth.oidc.callback');

});

Route::middleware('auth')->group(function () {
    Volt::route('verify-email', 'auth.verify-email')
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Volt::route('confirm-password', 'auth.confirm-password')
        ->name('password.confirm');
});

Route::post('logout', App\Livewire\Actions\Logout::class)
    ->name('logout');
