<?php

declare(strict_types=1);

use App\Jobs\FetchDeviceModelsJob;
use App\Models\DeviceModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    DeviceModel::truncate();

    // Mock palettes API to return empty array by default
    Http::fake([
        'usetrmnl.com/api/palettes' => Http::response([
            'data' => [],
        ], 200),
    ]);
});

test('fetch device models job can be dispatched', function (): void {
    $job = new FetchDeviceModelsJob();
    expect($job)->toBeInstanceOf(FetchDeviceModelsJob::class);
});

test('fetch device models job handles successful api response', function (): void {
    Http::fake([
        'usetrmnl.com/api/palettes' => Http::response(['data' => []], 200),
        config('services.trmnl.base_url').'/api/models' => Http::response([
            'data' => [
                [
                    'name' => 'test-model',
                    'label' => 'Test Model',
                    'description' => 'A test device model',
                    'width' => 800,
                    'height' => 480,
                    'colors' => 4,
                    'bit_depth' => 2,
                    'scale_factor' => 1.0,
                    'rotation' => 0,
                    'mime_type' => 'image/png',
                    'offset_x' => 0,
                    'offset_y' => 0,
                    'kind' => 'trmnl',
                    'published_at' => '2023-01-01T00:00:00Z',
                ],
            ],
        ], 200),
    ]);

    Log::shouldReceive('info')
        ->once()
        ->with('Successfully fetched and updated palettes', ['count' => 0]);

    Log::shouldReceive('info')
        ->once()
        ->with('Successfully fetched and updated device models', ['count' => 1]);

    $job = new FetchDeviceModelsJob();
    $job->handle();

    $deviceModel = DeviceModel::where('name', 'test-model')->first();
    expect($deviceModel)->not->toBeNull();
    expect($deviceModel->label)->toBe('Test Model');
    expect($deviceModel->description)->toBe('A test device model');
    expect($deviceModel->width)->toBe(800);
    expect($deviceModel->height)->toBe(480);
    expect($deviceModel->colors)->toBe(4);
    expect($deviceModel->bit_depth)->toBe(2);
    expect($deviceModel->scale_factor)->toBe(1.0);
    expect($deviceModel->rotation)->toBe(0);
    expect($deviceModel->mime_type)->toBe('image/png');
    expect($deviceModel->offset_x)->toBe(0);
    expect($deviceModel->offset_y)->toBe(0);
    // expect($deviceModel->kind)->toBe('trmnl');
    expect($deviceModel->source)->toBe('api');
});

test('fetch device models job handles multiple device models', function (): void {
    Http::fake([
        'usetrmnl.com/api/palettes' => Http::response(['data' => []], 200),
        config('services.trmnl.base_url').'/api/models' => Http::response([
            'data' => [
                [
                    'name' => 'model-1',
                    'label' => 'Model 1',
                    'description' => 'First model',
                    'width' => 800,
                    'height' => 480,
                    'colors' => 4,
                    'bit_depth' => 2,
                    'scale_factor' => 1.0,
                    'rotation' => 0,
                    'mime_type' => 'image/png',
                    'offset_x' => 0,
                    'offset_y' => 0,
                    'published_at' => '2023-01-01T00:00:00Z',
                ],
                [
                    'name' => 'model-2',
                    'label' => 'Model 2',
                    'description' => 'Second model',
                    'width' => 1200,
                    'height' => 800,
                    'colors' => 16,
                    'bit_depth' => 4,
                    'scale_factor' => 1.5,
                    'rotation' => 90,
                    'mime_type' => 'image/bmp',
                    'offset_x' => 10,
                    'offset_y' => 20,
                    'published_at' => '2023-01-02T00:00:00Z',
                ],
            ],
        ], 200),
    ]);

    Log::shouldReceive('info')
        ->once()
        ->with('Successfully fetched and updated palettes', ['count' => 0]);

    Log::shouldReceive('info')
        ->once()
        ->with('Successfully fetched and updated device models', ['count' => 2]);

    $job = new FetchDeviceModelsJob();
    $job->handle();

    expect(DeviceModel::where('name', 'model-1')->exists())->toBeTrue();
    expect(DeviceModel::where('name', 'model-2')->exists())->toBeTrue();
});

test('fetch device models job handles empty data array', function (): void {
    Http::fake([
        'usetrmnl.com/api/palettes' => Http::response(['data' => []], 200),
        config('services.trmnl.base_url').'/api/models' => Http::response([
            'data' => [],
        ], 200),
    ]);

    Log::shouldReceive('info')
        ->once()
        ->with('Successfully fetched and updated palettes', ['count' => 0]);

    Log::shouldReceive('info')
        ->once()
        ->with('Successfully fetched and updated device models', ['count' => 0]);

    $job = new FetchDeviceModelsJob();
    $job->handle();

    expect(DeviceModel::count())->toBe(0);
});

test('fetch device models job handles missing data field', function (): void {
    Http::fake([
        'usetrmnl.com/api/palettes' => Http::response(['data' => []], 200),
        config('services.trmnl.base_url').'/api/models' => Http::response([
            'message' => 'No data available',
        ], 200),
    ]);

    Log::shouldReceive('info')
        ->once()
        ->with('Successfully fetched and updated palettes', ['count' => 0]);

    Log::shouldReceive('info')
        ->once()
        ->with('Successfully fetched and updated device models', ['count' => 0]);

    $job = new FetchDeviceModelsJob();
    $job->handle();

    expect(DeviceModel::count())->toBe(0);
});

test('fetch device models job handles non-array data', function (): void {
    Http::fake([
        'usetrmnl.com/api/palettes' => Http::response(['data' => []], 200),
        config('services.trmnl.base_url').'/api/models' => Http::response([
            'data' => 'invalid-data',
        ], 200),
    ]);

    Log::shouldReceive('info')
        ->once()
        ->with('Successfully fetched and updated palettes', ['count' => 0]);

    Log::shouldReceive('error')
        ->once()
        ->with('Invalid response format from device models API', Mockery::type('array'));

    $job = new FetchDeviceModelsJob();
    $job->handle();

    expect(DeviceModel::count())->toBe(0);
});

test('fetch device models job handles api failure', function (): void {
    Http::fake([
        'usetrmnl.com/api/palettes' => Http::response(['data' => []], 200),
        config('services.trmnl.base_url').'/api/models' => Http::response([
            'error' => 'Internal Server Error',
        ], 500),
    ]);

    Log::shouldReceive('info')
        ->once()
        ->with('Successfully fetched and updated palettes', ['count' => 0]);

    Log::shouldReceive('error')
        ->once()
        ->with('Failed to fetch device models from API', [
            'status' => 500,
            'body' => '{"error":"Internal Server Error"}',
        ]);

    $job = new FetchDeviceModelsJob();
    $job->handle();

    expect(DeviceModel::count())->toBe(0);
});

test('fetch device models job handles network exception', function (): void {
    Http::fake([
        'usetrmnl.com/api/palettes' => Http::response(['data' => []], 200),
        config('services.trmnl.base_url').'/api/models' => function (): void {
            throw new Exception('Network connection failed');
        },
    ]);

    Log::shouldReceive('info')
        ->once()
        ->with('Successfully fetched and updated palettes', ['count' => 0]);

    Log::shouldReceive('error')
        ->once()
        ->with('Exception occurred while fetching device models', Mockery::type('array'));

    $job = new FetchDeviceModelsJob();
    $job->handle();

    expect(DeviceModel::count())->toBe(0);
});

test('fetch device models job handles device model with missing name', function (): void {
    Http::fake([
        'usetrmnl.com/api/palettes' => Http::response(['data' => []], 200),
        config('services.trmnl.base_url').'/api/models' => Http::response([
            'data' => [
                [
                    'label' => 'Model without name',
                    'description' => 'This model has no name',
                ],
            ],
        ], 200),
    ]);

    Log::shouldReceive('info')
        ->once()
        ->with('Successfully fetched and updated palettes', ['count' => 0]);

    Log::shouldReceive('warning')
        ->once()
        ->with('Device model data missing name field', Mockery::type('array'));

    Log::shouldReceive('info')
        ->once()
        ->with('Successfully fetched and updated device models', ['count' => 1]);

    $job = new FetchDeviceModelsJob();
    $job->handle();

    expect(DeviceModel::count())->toBe(0);
});

test('fetch device models job handles device model with partial data', function (): void {
    Http::fake([
        'usetrmnl.com/api/palettes' => Http::response(['data' => []], 200),
        config('services.trmnl.base_url').'/api/models' => Http::response([
            'data' => [
                [
                    'name' => 'minimal-model',
                    // Only name provided, other fields should use defaults
                ],
            ],
        ], 200),
    ]);

    Log::shouldReceive('info')
        ->once()
        ->with('Successfully fetched and updated palettes', ['count' => 0]);

    Log::shouldReceive('info')
        ->once()
        ->with('Successfully fetched and updated device models', ['count' => 1]);

    $job = new FetchDeviceModelsJob();
    $job->handle();

    $deviceModel = DeviceModel::where('name', 'minimal-model')->first();
    expect($deviceModel)->not->toBeNull();
    expect($deviceModel->label)->toBe('');
    expect($deviceModel->description)->toBe('');
    expect($deviceModel->width)->toBe(0);
    expect($deviceModel->height)->toBe(0);
    expect($deviceModel->colors)->toBe(0);
    expect($deviceModel->bit_depth)->toBe(0);
    expect($deviceModel->scale_factor)->toBe(1.0);
    expect($deviceModel->rotation)->toBe(0);
    expect($deviceModel->mime_type)->toBe('');
    expect($deviceModel->offset_x)->toBe(0);
    expect($deviceModel->offset_y)->toBe(0);
    expect($deviceModel->kind)->toBeNull();
    expect($deviceModel->source)->toBe('api');
});

test('fetch device models job updates existing device model', function (): void {
    // Create an existing device model
    $existingModel = DeviceModel::factory()->create([
        'name' => 'existing-model',
        'label' => 'Old Label',
        'width' => 400,
        'height' => 300,
    ]);

    Http::fake([
        'usetrmnl.com/api/palettes' => Http::response(['data' => []], 200),
        config('services.trmnl.base_url').'/api/models' => Http::response([
            'data' => [
                [
                    'name' => 'existing-model',
                    'label' => 'Updated Label',
                    'description' => 'Updated description',
                    'width' => 800,
                    'height' => 600,
                    'colors' => 4,
                    'bit_depth' => 2,
                    'scale_factor' => 1.0,
                    'rotation' => 0,
                    'mime_type' => 'image/png',
                    'offset_x' => 0,
                    'offset_y' => 0,
                    'published_at' => '2023-01-01T00:00:00Z',
                ],
            ],
        ], 200),
    ]);

    Log::shouldReceive('info')
        ->once()
        ->with('Successfully fetched and updated palettes', ['count' => 0]);

    Log::shouldReceive('info')
        ->once()
        ->with('Successfully fetched and updated device models', ['count' => 1]);

    $job = new FetchDeviceModelsJob();
    $job->handle();

    $existingModel->refresh();
    expect($existingModel->label)->toBe('Updated Label');
    expect($existingModel->description)->toBe('Updated description');
    expect($existingModel->width)->toBe(800);
    expect($existingModel->height)->toBe(600);
    expect($existingModel->source)->toBe('api');
});

test('fetch device models job handles processing exception for individual model', function (): void {
    Http::fake([
        'usetrmnl.com/api/palettes' => Http::response(['data' => []], 200),
        config('services.trmnl.base_url').'/api/models' => Http::response([
            'data' => [
                [
                    'name' => 'valid-model',
                    'label' => 'Valid Model',
                    'width' => 800,
                    'height' => 480,
                ],
                [
                    'name' => null, // This will cause an exception in processing
                    'label' => 'Invalid Model',
                ],
            ],
        ], 200),
    ]);

    Log::shouldReceive('info')
        ->once()
        ->with('Successfully fetched and updated palettes', ['count' => 0]);

    Log::shouldReceive('warning')
        ->once()
        ->with('Device model data missing name field', Mockery::type('array'));

    Log::shouldReceive('info')
        ->once()
        ->with('Successfully fetched and updated device models', ['count' => 2]);

    $job = new FetchDeviceModelsJob();
    $job->handle();

    // Should still create the valid model
    expect(DeviceModel::where('name', 'valid-model')->exists())->toBeTrue();
    expect(DeviceModel::count())->toBe(1);
});
