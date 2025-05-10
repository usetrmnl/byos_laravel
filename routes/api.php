<?php

use App\Jobs\GenerateScreenJob;
use App\Jobs\GeneratePluginJob;
use App\Models\Device;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
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
    ]);

    // Get current screen image from mirror device or continue if not available
    if (! $image_uuid = $device->mirrorDevice?->current_screen_image) {
        $refreshTimeOverride = null;
        $nextPlaylistItem = $device->getNextPlaylistItem();
        // Skip if cloud proxy is enabled for the device
        if (! $device->proxy_cloud && $nextPlaylistItem) {
            $refreshTimeOverride = $nextPlaylistItem->playlist()->first()->refresh_time;
            $plugin = $nextPlaylistItem->plugin;

            // Check and update stale data if needed
            if ($plugin->isDataStale() || $plugin->current_image == null) {
                $plugin->updateDataPayload();

                if ($plugin->render_markup) {
                    $markup = Blade::render($plugin->render_markup, ['data' => $plugin->data_payload]);
                } elseif ($plugin->render_markup_view) {
                    $markup = view($plugin->render_markup_view, ['data' => $plugin->data_payload])->render();
                }

                GeneratePluginJob::dispatchSync($plugin->id, $markup);
            }

            $plugin->refresh();

            if ($plugin->current_image != null)
            {
                $nextPlaylistItem->update(['last_displayed_at' => now()]);
                $device->update(['current_screen_image' => $plugin->current_image]);
            }
        }

        $device->refresh();
        $image_uuid = $device->current_screen_image;
    }
    if (! $image_uuid) {
        $image_path = 'images/setup-logo.bmp';
        $filename = 'setup-logo.bmp';
    } else {
        if (file_exists(storage_path('app/public/images/generated/'.$image_uuid.'.bmp'))) {
            $image_path = 'images/generated/'.$image_uuid.'.bmp';
        } elseif (file_exists(storage_path('app/public/images/generated/'.$image_uuid.'.png'))) {
            $image_path = 'images/generated/'.$image_uuid.'.png';
        } else {
            $image_path = 'images/generated/'.$image_uuid.'.bmp';
        }
        $filename = basename($image_path);
    }

    $response = [
        'status' => 0,
        'image_url' => url('storage/'.$image_path),
        'filename' => $filename,
        'refresh_rate' => $refreshTimeOverride ?? $device->default_refresh_interval,
        'reset_firmware' => false,
        'update_firmware' => $device->update_firmware,
        'firmware_url' => $device->firmware_url,
        'special_function' => 'sleep',
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

    $logs = $request->json('log.logs_array', []);
    foreach ($logs as $log) {
        \Log::info('Device Log', $log);
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
        'last_rssi_level as rssi'
    ]);

    return response()->json([
        'data' => $devices
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

    GenerateScreenJob::dispatchSync($deviceId, $view);

    response()->json([
        'message' => 'success',
    ]);
})
    ->name('display.update')
    ->middleware('auth:sanctum', 'ability:update-screen');

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
            'updated_at',
        ]),
    );
})
    ->name('display.status')
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
        if (file_exists(storage_path('app/public/images/generated/'.$image_uuid.'.bmp'))) {
            $image_path = 'images/generated/'.$image_uuid.'.bmp';
        } elseif (file_exists(storage_path('app/public/images/generated/'.$image_uuid.'.png'))) {
            $image_path = 'images/generated/'.$image_uuid.'.png';
        } else {
            $image_path = 'images/generated/'.$image_uuid.'.bmp';
        }
        $filename = basename($image_path);
    }

    $response = [
        'status' => 0,
        'image_url' => url('storage/'.$image_path),
        'filename' => $filename,
        'refresh_rate' => $refreshTimeOverride ?? $device->default_refresh_interval,
        'reset_firmware' => false,
        'update_firmware' => false,
        'firmware_url' => $device->firmware_url,
        'special_function' => 'sleep',
    ];

    if (config('services.trmnl.image_url_timeout')) {
        $response['image_url_timeout'] = config('services.trmnl.image_url_timeout');
    }

    return response()->json($response);
});

Route::post('custom_plugins/{plugin_uuid}', function (string $plugin_uuid) {
    $plugin = \App\Models\Plugin::where('uuid', $plugin_uuid)->firstOrFail();

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
