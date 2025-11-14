<?php

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use Symfony\Component\Yaml\Yaml;

beforeEach(function (): void {
    Cache::flush();
});

it('can render catalog component', function (): void {
    // Mock empty catalog response
    Http::fake([
        config('app.catalog_url') => Http::response('', 200),
    ]);

    Livewire::withoutLazyLoading();

    $component = Volt::test('catalog.index');

    $component->assertSee('No plugins available');
});

it('loads plugins from catalog URL', function (): void {
    // Clear cache first to ensure fresh data
    Cache::forget('catalog_plugins');

    // Mock the HTTP response for the catalog URL
    $catalogData = [
        'test-plugin' => [
            'name' => 'Test Plugin',
            'author' => ['name' => 'Test Author', 'github' => 'testuser'],
            'author_bio' => [
                'description' => 'A test plugin',
                'learn_more_url' => 'https://example.com',
            ],
            'license' => 'MIT',
            'trmnlp' => [
                'zip_url' => 'https://example.com/plugin.zip',
            ],
            'byos' => [
                'byos_laravel' => [
                    'compatibility' => true,
                ],
            ],
            'logo_url' => 'https://example.com/logo.png',
        ],
    ];

    $yamlContent = Yaml::dump($catalogData);

    // Override the default mock with specific data
    Http::fake([
        config('app.catalog_url') => Http::response($yamlContent, 200),
    ]);

    Livewire::withoutLazyLoading();

    $component = Volt::test('catalog.index');

    $component->assertSee('Test Plugin');
    $component->assertSee('testuser');
    $component->assertSee('A test plugin');
    $component->assertSee('MIT');
});

it('shows error when plugin not found', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::withoutLazyLoading();

    $component = Volt::test('catalog.index');

    $component->call('installPlugin', 'non-existent-plugin');

    // The component should dispatch an error notification
    $component->assertHasErrors();
});

it('shows error when zip_url is missing', function (): void {
    $user = User::factory()->create();

    // Mock the HTTP response for the catalog URL without zip_url
    $catalogData = [
        'test-plugin' => [
            'name' => 'Test Plugin',
            'author' => ['name' => 'Test Author'],
            'author_bio' => ['description' => 'A test plugin'],
            'license' => 'MIT',
            'trmnlp' => [],
        ],
    ];

    $yamlContent = Yaml::dump($catalogData);

    Http::fake([
        config('app.catalog_url') => Http::response($yamlContent, 200),
    ]);

    $this->actingAs($user);

    Livewire::withoutLazyLoading();

    $component = Volt::test('catalog.index');

    $component->call('installPlugin', 'test-plugin');

    // The component should dispatch an error notification
    $component->assertHasErrors();

});
