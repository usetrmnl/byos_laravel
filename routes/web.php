<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/preferences');
    Volt::route('settings/preferences', 'settings.preferences')->name('settings.preferences');
    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
    Volt::route('settings/support', 'settings.support')->name('settings.support');

    Volt::route('/dashboard', 'device-dashboard')->name('dashboard');

    Volt::route('/devices', 'devices.manage')->name('devices');
    Volt::route('/devices/{device}/configure', 'devices.configure')->name('devices.configure');
    Volt::route('/devices/{device}/logs', 'devices.logs')->name('devices.logs');

    Volt::route('plugins', 'plugins.index')->name('plugins.index');

    Volt::route('plugins/recipe/{plugin}', 'plugins.recipe')->name('plugins.recipe');
    Volt::route('plugins/markup', 'plugins.markup')->name('plugins.markup');
    Volt::route('plugins/api', 'plugins.api')->name('plugins.api');
    Volt::route('playlists', 'playlists.index')->name('playlists.index');
});

require __DIR__.'/auth.php';
