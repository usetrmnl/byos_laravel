<?php

declare(strict_types=1);

use App\Models\DeviceModel;
use Illuminate\Support\Facades\Config;

test('device model has required attributes', function (): void {
    $deviceModel = DeviceModel::factory()->create([
        'name' => 'Test Model',
        'width' => 800,
        'height' => 480,
        'colors' => 4,
        'bit_depth' => 2,
        'scale_factor' => 1.0,
        'rotation' => 0,
        'offset_x' => 0,
        'offset_y' => 0,
    ]);

    expect($deviceModel->name)->toBe('Test Model');
    expect($deviceModel->width)->toBe(800);
    expect($deviceModel->height)->toBe(480);
    expect($deviceModel->colors)->toBe(4);
    expect($deviceModel->bit_depth)->toBe(2);
    expect($deviceModel->scale_factor)->toBe(1.0);
    expect($deviceModel->rotation)->toBe(0);
    expect($deviceModel->offset_x)->toBe(0);
    expect($deviceModel->offset_y)->toBe(0);
});

test('device model casts attributes correctly', function (): void {
    $deviceModel = DeviceModel::factory()->create([
        'width' => '800',
        'height' => '480',
        'colors' => '4',
        'bit_depth' => '2',
        'scale_factor' => '1.5',
        'rotation' => '90',
        'offset_x' => '10',
        'offset_y' => '20',
    ]);

    expect($deviceModel->width)->toBeInt();
    expect($deviceModel->height)->toBeInt();
    expect($deviceModel->colors)->toBeInt();
    expect($deviceModel->bit_depth)->toBeInt();
    expect($deviceModel->scale_factor)->toBeFloat();
    expect($deviceModel->rotation)->toBeInt();
    expect($deviceModel->offset_x)->toBeInt();
    expect($deviceModel->offset_y)->toBeInt();
});

test('get color depth attribute returns correct format for bit depth 2', function (): void {
    $deviceModel = DeviceModel::factory()->create(['bit_depth' => 2]);

    expect($deviceModel->getColorDepthAttribute())->toBe('2bit');
});

test('get color depth attribute returns correct format for bit depth 4', function (): void {
    $deviceModel = DeviceModel::factory()->create(['bit_depth' => 4]);

    expect($deviceModel->getColorDepthAttribute())->toBe('4bit');
});

test('get color depth attribute returns 4bit for bit depth greater than 4', function (): void {
    $deviceModel = DeviceModel::factory()->create(['bit_depth' => 8]);

    expect($deviceModel->getColorDepthAttribute())->toBe('4bit');
});

test('get color depth attribute returns null when bit depth is null', function (): void {
    $deviceModel = new DeviceModel(['bit_depth' => null]);

    expect($deviceModel->getColorDepthAttribute())->toBeNull();
});

test('get scale level attribute returns null for width 800 or less', function (): void {
    $deviceModel = DeviceModel::factory()->create(['width' => 800]);

    expect($deviceModel->getScaleLevelAttribute())->toBeNull();
});

test('get scale level attribute returns large for width between 801 and 1000', function (): void {
    $deviceModel = DeviceModel::factory()->create(['width' => 900]);

    expect($deviceModel->getScaleLevelAttribute())->toBe('large');
});

test('get scale level attribute returns xlarge for width between 1001 and 1400', function (): void {
    $deviceModel = DeviceModel::factory()->create(['width' => 1200]);

    expect($deviceModel->getScaleLevelAttribute())->toBe('xlarge');
});

test('get scale level attribute returns xxlarge for width greater than 1400', function (): void {
    $deviceModel = DeviceModel::factory()->create(['width' => 1500]);

    expect($deviceModel->getScaleLevelAttribute())->toBe('xxlarge');
});

test('get scale level attribute returns null when width is null', function (): void {
    $deviceModel = new DeviceModel(['width' => null]);

    expect($deviceModel->getScaleLevelAttribute())->toBeNull();
});

test('device model factory creates valid data', function (): void {
    $deviceModel = DeviceModel::factory()->create();

    expect($deviceModel->name)->not->toBeEmpty();
    expect($deviceModel->width)->toBeInt();
    expect($deviceModel->height)->toBeInt();
    expect($deviceModel->colors)->toBeInt();
    expect($deviceModel->bit_depth)->toBeInt();
    expect($deviceModel->scale_factor)->toBeFloat();
    expect($deviceModel->rotation)->toBeInt();
    expect($deviceModel->offset_x)->toBeInt();
    expect($deviceModel->offset_y)->toBeInt();
});

test('css_name returns og when puppeteer_window_size_strategy is v1', function (): void {
    Config::set('app.puppeteer_window_size_strategy', 'v1');

    $deviceModel = DeviceModel::factory()->create(['css_name' => 'my_device']);

    expect($deviceModel->css_name)->toBe('og');
});

test('css_name returns db value when puppeteer_window_size_strategy is v2', function (): void {
    Config::set('app.puppeteer_window_size_strategy', 'v2');

    $deviceModel = DeviceModel::factory()->create(['css_name' => 'my_device']);

    expect($deviceModel->css_name)->toBe('my_device');
});

test('css_name returns null when puppeteer_window_size_strategy is v2 and db value is null', function (): void {
    Config::set('app.puppeteer_window_size_strategy', 'v2');

    $deviceModel = DeviceModel::factory()->create(['css_name' => null]);

    expect($deviceModel->css_name)->toBeNull();
});
