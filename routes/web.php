<?php

use App\Models\Plugin;
use Illuminate\Http\Request;
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

    Volt::route('/device-models', 'device-models.index')->name('device-models.index');
    Volt::route('/device-palettes', 'device-palettes.index')->name('device-palettes.index');

    Volt::route('plugins', 'plugins.index')->name('plugins.index');

    Volt::route('plugins/recipe/{plugin}', 'plugins.recipe')->name('plugins.recipe');
    Volt::route('plugins/markup', 'plugins.markup')->name('plugins.markup');
    Volt::route('plugins/api', 'plugins.api')->name('plugins.api');
    Volt::route('plugins/image-webhook', 'plugins.image-webhook')->name('plugins.image-webhook');
    Volt::route('plugins/image-webhook/{plugin}', 'plugins.image-webhook-instance')->name('plugins.image-webhook-instance');
    Volt::route('playlists', 'playlists.index')->name('playlists.index');

    Route::get('plugin_settings/{trmnlp_id}/edit', function (Request $request, string $trmnlp_id) {
        $plugin = Plugin::query()
            ->where('user_id', $request->user()->id)
            ->where('trmnlp_id', $trmnlp_id)->firstOrFail();

        return redirect()->route('plugins.recipe', ['plugin' => $plugin]);
    });
});

require __DIR__.'/auth.php';
