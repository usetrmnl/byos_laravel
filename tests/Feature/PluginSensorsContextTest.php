<?php

use App\Enums\DeviceSensorKind;
use App\Models\Device;
use App\Models\DeviceSensor;
use App\Models\Plugin;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('plugin render exposes sensor data in trmnl.sensors for liquid templates', function () {
    $device = Device::factory()->create();

    DeviceSensor::factory()->create([
        'device_id' => $device->id,
        'kind' => DeviceSensorKind::TEMPERATURE,
        'value' => 21.5,
        'unit' => 'celcius',
    ]);

    $plugin = Plugin::factory()->create([
        'plugin_type' => 'recipe',
        'markup_language' => 'liquid',
        'render_markup' => '{{ trmnl.sensors.latest.temperature.value }} {{ trmnl.sensors.latest.temperature.unit }}',
    ]);

    $output = $plugin->render(device: $device);

    expect($output)->toContain('21.5 celcius');
});
