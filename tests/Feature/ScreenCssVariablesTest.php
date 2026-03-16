<?php

use App\Models\Device;
use App\Models\DeviceModel;
use Illuminate\Support\Facades\Config;

beforeEach(function (): void {
    Config::set('app.puppeteer_window_size_strategy', 'v2');
});

test('screen component outputs :root with --screen-w and --screen-h when cssVariables are passed', function (): void {
    $html = view('trmnl-layouts.single', [
        'slot' => '<div>test</div>',
        'cssVariables' => [
            '--screen-w' => '800px',
            '--screen-h' => '480px',
        ],
    ])->render();

    expect($html)->toContain(':root');
    expect($html)->toContain('--screen-w: 800px');
    expect($html)->toContain('--screen-h: 480px');
});

test('DeviceModel css_variables attribute merges --screen-w and --screen-h from dimensions when not set', function (): void {
    $deviceModel = DeviceModel::factory()->create([
        'width' => 800,
        'height' => 480,
        'css_variables' => null,
    ]);

    $vars = $deviceModel->css_variables;

    expect($vars)->toHaveKey('--screen-w', '800px');
    expect($vars)->toHaveKey('--screen-h', '480px');
});

test('DeviceModel css_variables attribute does not override --screen-w and --screen-h when already set', function (): void {
    $deviceModel = DeviceModel::factory()->create([
        'width' => 800,
        'height' => 480,
        'css_variables' => [
            '--screen-w' => '1200px',
            '--screen-h' => '900px',
        ],
    ]);

    $vars = $deviceModel->css_variables;

    expect($vars['--screen-w'])->toBe('1200px');
    expect($vars['--screen-h'])->toBe('900px');
});

test('DeviceModel css_variables attribute fills only missing --screen-w or --screen-h from dimensions', function (): void {
    $deviceModel = DeviceModel::factory()->create([
        'width' => 800,
        'height' => 480,
        'css_variables' => [
            '--screen-w' => '640px',
        ],
    ]);

    $vars = $deviceModel->css_variables;

    expect($vars['--screen-w'])->toBe('640px');
    expect($vars['--screen-h'])->toBe('480px');
});

test('DeviceModel css_variables attribute returns raw vars when strategy is not v2', function (): void {
    Config::set('app.puppeteer_window_size_strategy', 'v1');

    $deviceModel = DeviceModel::factory()->create([
        'width' => 800,
        'height' => 480,
        'css_variables' => ['--custom' => 'value'],
    ]);

    $vars = $deviceModel->css_variables;

    expect($vars)->toBe(['--custom' => 'value']);
});

test('device model css_variables are available via device relationship for rendering', function (): void {
    Config::set('app.puppeteer_window_size_strategy', 'v2');

    $deviceModel = DeviceModel::factory()->create([
        'width' => 800,
        'height' => 480,
        'css_variables' => null,
    ]);
    $device = Device::factory()->create([
        'device_model_id' => $deviceModel->id,
    ]);
    $device->load('deviceModel');

    $vars = $device->deviceModel?->css_variables ?? [];

    expect($vars)->toHaveKey('--screen-w', '800px');
    expect($vars)->toHaveKey('--screen-h', '480px');
});
