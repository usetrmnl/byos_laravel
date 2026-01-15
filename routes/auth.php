<?php

use App\Http\Controllers\Auth\OidcController;
use Illuminate\Support\Facades\Route;

// Other Auth routes are handled by Fortify
Route::middleware('guest')->group(function () {
    // OIDC authentication routes
    Route::get('auth/oidc/redirect', [OidcController::class, 'redirect'])
        ->name('auth.oidc.redirect');

    Route::get('auth/oidc/callback', [OidcController::class, 'callback'])
        ->name('auth.oidc.callback');

});
