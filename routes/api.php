<?php

use App\Jobs\GenerateScreenJob;
use App\Models\Device;
use App\Models\DeviceLog;
use App\Models\Plugin;
use App\Models\User;
use App\Services\ImageGenerationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

Route::get('/display', function (Request $request) {
    $mac_address = $request->header('id');
    $access_token = $request->header('access-token');
    $device = Device::where('mac_address', $mac_address)
        ->where('api_key', $access_token)
        ->first();

    if (! $device) {
        // Check if there's a user with assign_new_devices enabled
        $auto_assign_user = User::where('assign_new_devices', true)->first();

        if ($auto_assign_user) {
            // Create a new device and assign it to this user
            $device = Device::create([
                'mac_address' => $mac_address,
                'api_key' => $access_token,
                'user_id' => $auto_assign_user->id,
                'name' => "{$auto_assign_user->name}'s TRMNL",
                'friendly_id' => Str::random(6),
                'default_refresh_interval' => 900,
                'mirror_device_id' => $auto_assign_user->assign_new_device_id,
            ]);
        } else {
            return response()->json([
                'message' => 'MAC Address not registered or invalid access token',
            ], 404);
        }
    }

    $device->update([
        'last_rssi_level' => $request->header('rssi'),
        'last_battery_voltage' => $request->header('battery_voltage'),
        'last_firmware_version' => $request->header('fw-version'),
        'last_refreshed_at' => now(),
    ]);

    if ($request->hasHeader('battery-percent')) {
        $batteryPercent = (int) $request->header('battery-percent');
        $batteryVoltage = $device->calculateVoltageFromPercent($batteryPercent);
        $device->update([
            'last_battery_voltage' => $batteryVoltage,
        ]);
    }

    if ($device->isPauseActive()) {
        $image_path = 'images/sleep.png';
        $filename = 'sleep.png';
        $refreshTimeOverride = (int) now()->diffInSeconds($device->pause_until);
    } elseif ($device->isSleepModeActive()) {
        $image_path = 'images/sleep.png';
        $filename = 'sleep.png';
        $refreshTimeOverride = $device->getSleepModeEndsInSeconds() ?? $device->default_refresh_interval;
    } else {
        // Get current screen image from a mirror device or continue if not available
        if (! $image_uuid = $device->mirrorDevice?->current_screen_image) {
            $refreshTimeOverride = null;
            // Skip if cloud proxy is enabled for the device
            if (! $device->proxy_cloud || $device->getNextPlaylistItem()) {
                $playlistItem = $device->getNextPlaylistItem();

                if ($playlistItem && ! $playlistItem->isMashup()) {
                    $refreshTimeOverride = $playlistItem->playlist()->first()->refresh_time;
                    $plugin = $playlistItem->plugin;

                    // Reset cache if Devices with different dimensions exist
                    ImageGenerationService::resetIfNotCacheable($plugin);

                    // Check and update stale data if needed
                    if ($plugin->isDataStale() || $plugin->current_image === null) {
                        $plugin->updateDataPayload();
                        $markup = $plugin->render();

                        GenerateScreenJob::dispatchSync($device->id, $plugin->id, $markup);
                    }

                    $plugin->refresh();

                    if ($plugin->current_image !== null) {
                        $playlistItem->update(['last_displayed_at' => now()]);
                        $device->update(['current_screen_image' => $plugin->current_image]);
                    }
                } elseif ($playlistItem) {
                    $refreshTimeOverride = $playlistItem->playlist()->first()->refresh_time;

                    // Get all plugins for the mashup
                    $plugins = Plugin::whereIn('id', $playlistItem->getMashupPluginIds())->get();

                    foreach ($plugins as $plugin) {
                        // Reset cache if Devices with different dimensions exist
                        ImageGenerationService::resetIfNotCacheable($plugin);
                        if ($plugin->isDataStale() || $plugin->current_image === null) {
                            $plugin->updateDataPayload();
                        }
                    }

                    $markup = $playlistItem->render();
                    GenerateScreenJob::dispatchSync($device->id, null, $markup);

                    $device->refresh();

                    if ($device->current_screen_image !== null) {
                        $playlistItem->update(['last_displayed_at' => now()]);
                    }
                }
            }

            $device->refresh();
            $image_uuid = $device->current_screen_image;
        }
        if (! $image_uuid) {
            $image_path = 'images/setup-logo.bmp';
            $filename = 'setup-logo.bmp';
        } else {
            if (isset($device->last_firmware_version)
                && version_compare($device->last_firmware_version, '1.5.2', '<')
                && Storage::disk('public')->exists('images/generated/'.$image_uuid.'.bmp')) {
                $image_path = 'images/generated/'.$image_uuid.'.bmp';
            } elseif (Storage::disk('public')->exists('images/generated/'.$image_uuid.'.png')) {
                $image_path = 'images/generated/'.$image_uuid.'.png';
            } else {
                $image_path = 'images/generated/'.$image_uuid.'.bmp';
            }
            $filename = basename($image_path);
        }
    }

    $response = [
        'status' => 0,
        'image_url' => url('storage/'.$image_path),
        'filename' => $filename,
        'refresh_rate' => $refreshTimeOverride ?? $device->default_refresh_interval,
        'reset_firmware' => false,
        'update_firmware' => $device->update_firmware,
        'firmware_url' => $device->firmware_url,
        'special_function' => $device->special_function ?? 'sleep',
    ];

    if (config('services.trmnl.image_url_timeout')) {
        $response['image_url_timeout'] = config('services.trmnl.image_url_timeout');
    }
    // If update_firmware is true, reset it after returning it, to avoid upgrade loop
    if ($device->update_firmware) {
        $device->resetUpdateFirmwareFlag();
    }

    return response()->json($response);
});

Route::get('/setup', function (Request $request) {
    $mac_address = $request->header('id');

    if (! $mac_address) {
        return response()->json([
            'status' => 404,
            'message' => 'MAC Address not registered',
        ], 404);
    }

    $device = Device::where('mac_address', $mac_address)->first();

    if (! $device) {
        // Check if there's a user with assign_new_devices enabled
        $auto_assign_user = User::where('assign_new_devices', true)->first();

        if ($auto_assign_user) {
            // Create a new device and assign it to this user
            $device = Device::create([
                'mac_address' => $mac_address,
                'api_key' => Str::random(22),
                'user_id' => $auto_assign_user->id,
                'name' => "{$auto_assign_user->name}'s TRMNL",
                'friendly_id' => Str::random(6),
                'default_refresh_interval' => 900,
                'mirror_device_id' => $auto_assign_user->assign_new_device_id,
            ]);
        } else {
            return response()->json([
                'status' => 404,
                'message' => 'MAC Address not registered or invalid access token',
            ], 404);
        }
    }

    return response()->json([
        'status' => 200,
        'api_key' => $device->api_key,
        'friendly_id' => $device->friendly_id,
        'image_url' => url('storage/images/setup-logo.png'),
        'message' => 'Welcome to TRMNL BYOS',
    ]);
});

Route::post('/log', function (Request $request) {
    //    $mac_address = $request->header('id');
    $access_token = $request->header('access-token');

    $device = Device::where('api_key', $access_token) // where('mac_address', $mac_address)
        ->first();

    if (! $device) {
        return response()->json([
            'status' => 404,
            'message' => 'Device not found or invalid access token',
        ], 404);
    }

    $device->update([
        'last_log_request' => $request->json()->all(),
    ]);

    $logs = [];
    // Revised format: {"logs": [...]}
    if ($request->has('logs')) {
        $logs = $request->json('logs', []);
    }
    // Fall back to old format: {"log": {"logs_array": [...]}}
    elseif ($request->has('log.logs_array')) {
        $logs = $request->json('log.logs_array', []);
    }

    foreach ($logs as $log) {
        Log::info('Device Log', $log);
        DeviceLog::create([
            'device_id' => $device->id,
            'device_timestamp' => $log['creation_timestamp'] ?? now(),
            'log_entry' => $log,
        ]);
    }

    return response()->json([
        'status' => 200,
    ]);
});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/devices', function (Request $request) {
    $devices = $request->user()->devices()->get([
        'id',
        'name',
        'friendly_id',
        'mac_address',
        'last_battery_voltage as battery_voltage',
        'last_rssi_level as rssi',
    ]);

    return response()->json([
        'data' => $devices,
    ]);
})->middleware('auth:sanctum');

Route::post('/display/update', function (Request $request) {
    $request->validate([
        'device_id' => 'required|exists:devices,id',
        'markup' => 'required|string',
    ]);

    $deviceId = $request['device_id'];
    abort_unless($request->user()->devices->contains($deviceId), 403);

    $view = Blade::render($request['markup']);

    GenerateScreenJob::dispatchSync($deviceId, null, $view);

    response()->json([
        'message' => 'success',
    ]);
})
    ->name('display.update')
    ->middleware('auth:sanctum', 'ability:update-screen');

Route::post('/screens', function (Request $request) {
    $mac_address = $request->header('id');
    $access_token = $request->header('access-token');
    $device = Device::where('mac_address', $mac_address)
        ->where('api_key', $access_token)
        ->first();

    if (! $device) {
        return response()->json([
            'message' => 'MAC Address not registered or invalid access token',
        ], 404);
    }

    $request->validate([
        'image' => 'array|required',
        'image.content' => 'string|required',
        'image.file_name' => 'string',
    ]);
    $content = $request['image']['content'];

    $view = Blade::render($content);
    GenerateScreenJob::dispatchSync($device->id, null, $view);

    return response()->json([
        'message' => 'success',
    ]);
})->name('screens.update');

Route::get('/display/status', function (Request $request) {
    $request->validate([
        'device_id' => 'required|exists:devices,id',
    ]);

    $deviceId = $request['device_id'];
    abort_unless($request->user()->devices->contains($deviceId), 403);

    return response()->json(
        Device::find($deviceId)->only([
            'id',
            'mac_address',
            'name',
            'friendly_id',
            'last_rssi_level',
            'last_battery_voltage',
            'last_firmware_version',
            'battery_percent',
            'wifi_strength',
            'current_screen_image',
            'default_refresh_interval',
            'sleep_mode_enabled',
            'sleep_mode_from',
            'sleep_mode_to',
            'special_function',
            'pause_until',
            'updated_at',
        ]),
    );
})
    ->name('display.status')
    ->middleware('auth:sanctum');

Route::post('/display/status', function (Request $request) {
    $request->validate([
        'device_id' => 'required|exists:devices,id',
        'name' => 'string|max:255',
        'default_refresh_interval' => 'integer|min:1',
        'sleep_mode_enabled' => 'boolean',
        'sleep_mode_from' => 'nullable|date_format:H:i',
        'sleep_mode_to' => 'nullable|date_format:H:i',
        'pause_until' => 'nullable|date|after:now',
    ]);

    $deviceId = $request['device_id'];
    abort_unless($request->user()->devices->contains($deviceId), 403);

    $fieldsToUpdate = $request->only(['name', 'default_refresh_interval', 'sleep_mode_enabled', 'sleep_mode_from', 'sleep_mode_to', 'pause_until']);
    Device::find($deviceId)->update($fieldsToUpdate);

    return response()->json(
        Device::find($deviceId)->only([
            'id',
            'mac_address',
            'name',
            'friendly_id',
            'last_rssi_level',
            'last_battery_voltage',
            'last_firmware_version',
            'battery_percent',
            'wifi_strength',
            'current_screen_image',
            'default_refresh_interval',
            'sleep_mode_enabled',
            'sleep_mode_from',
            'sleep_mode_to',
            'special_function',
            'pause_until',
            'updated_at',
        ]),
    );
})
    ->name('display.status.post')
    ->middleware('auth:sanctum');

Route::get('/current_screen', function (Request $request) {
    $access_token = $request->header('access-token');
    $device = Device::where('api_key', $access_token)->first();

    if (! $device) {
        return response()->json([
            'status' => 404,
            'message' => 'Device not found or invalid access token',
        ], 404);
    }

    $image_uuid = $device->current_screen_image;

    if (! $image_uuid) {
        $image_path = 'images/setup-logo.bmp';
        $filename = 'setup-logo.bmp';
    } else {
        if (isset($device->last_firmware_version)
            && version_compare($device->last_firmware_version, '1.5.2', '<')
            && Storage::disk('public')->exists('images/generated/'.$image_uuid.'.bmp')) {
            $image_path = 'images/generated/'.$image_uuid.'.bmp';
        } elseif (Storage::disk('public')->exists('images/generated/'.$image_uuid.'.png')) {
            $image_path = 'images/generated/'.$image_uuid.'.png';
        } else {
            $image_path = 'images/generated/'.$image_uuid.'.bmp';
        }
        $filename = basename($image_path);
    }

    $response = [
        'status' => 200,
        'image_url' => url('storage/'.$image_path),
        'filename' => $filename,
        'refresh_rate' => $refreshTimeOverride ?? $device->default_refresh_interval,
        'reset_firmware' => false,
        'update_firmware' => false,
        'firmware_url' => $device->firmware_url,
        'special_function' => $device->special_function ?? 'sleep',
    ];

    if (config('services.trmnl.image_url_timeout')) {
        $response['image_url_timeout'] = config('services.trmnl.image_url_timeout');
    }

    return response()->json($response);
});

Route::post('custom_plugins/{plugin_uuid}', function (string $plugin_uuid) {
    $plugin = Plugin::where('uuid', $plugin_uuid)->firstOrFail();

    // Check if plugin uses webhook strategy
    if ($plugin->data_strategy !== 'webhook') {
        return response()->json(['error' => 'Plugin does not use webhook strategy'], 400);
    }

    $request = request();
    if (! $request->has('merge_variables')) {
        return response()->json(['error' => 'Request must contain merge_variables key'], 400);
    }

    $plugin->update([
        'data_payload' => $request->input('merge_variables'),
        'data_payload_updated_at' => now(),
    ]);

    return response()->json(['message' => 'Data updated successfully']);
})->name('api.custom_plugins.webhook');
