<?php

use App\Jobs\GenerateScreenJob;
use App\Models\Device;
use App\Models\User;
use Illuminate\Http\Request;
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

    $refreshTimeOverride = null;
    // Skip if cloud proxy is enabled for device
    if (! $device->proxy_cloud || $device->getNextPlaylistItem()) {
        $playlistItem = $device->getNextPlaylistItem();

        if ($playlistItem) {
            $refreshTimeOverride = $playlistItem->playlist()->first()->refresh_time;

            $plugin = $playlistItem->plugin;

            // Check and update stale data if needed
            if ($plugin->isDataStale()) {
                $plugin->updateDataPayload();
            }

            $playlistItem->update(['last_displayed_at' => now()]);
            if ($plugin->render_markup) {
                $markup = Blade::render($plugin->render_markup, ['data' => $plugin->data_payload]);
            } elseif ($plugin->render_markup_view) {
                $markup = view($plugin->render_markup_view, ['data' => $plugin->data_payload])->render();
            }

            GenerateScreenJob::dispatchSync($device->id, $markup);
        }
    }

    $device->refresh();
    $image_uuid = $device->current_screen_image;
    if (! $image_uuid) {
        $image_path = 'images/setup-logo.bmp';
        $filename = 'setup-logo.bmp';
    } else {
        $image_path = 'images/generated/'.$image_uuid.'.bmp';
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
