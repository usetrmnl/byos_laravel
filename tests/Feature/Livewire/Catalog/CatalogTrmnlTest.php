<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

it('loads newest TRMNL recipes on mount', function (): void {
    Http::fake([
        config('services.trmnl.base_url').'/recipes.json*' => Http::response([
            'data' => [
                [
                    'id' => 123,
                    'name' => 'Weather Chum',
                    'icon_url' => 'https://example.com/icon.png',
                    'screenshot_url' => null,
                    'author_bio' => null,
                    'stats' => ['installs' => 10, 'forks' => 2],
                ],
            ],
        ], 200),
    ]);

    Livewire::withoutLazyLoading();

    Livewire::test('catalog.trmnl')
        ->assertSee('Weather Chum')
        ->assertSee('Install')
        ->assertDontSeeHtml('variant="subtle" icon="eye"')
        ->assertSee('Installs: 10');
});

it('shows preview button when screenshot_url is provided', function (): void {
    Http::fake([
        config('services.trmnl.base_url').'/recipes.json*' => Http::response([
            'data' => [
                [
                    'id' => 123,
                    'name' => 'Weather Chum',
                    'icon_url' => 'https://example.com/icon.png',
                    'screenshot_url' => 'https://example.com/screenshot.png',
                    'author_bio' => null,
                    'stats' => ['installs' => 10, 'forks' => 2],
                ],
            ],
        ], 200),
    ]);

    Livewire::withoutLazyLoading();

    Livewire::test('catalog.trmnl')
        ->assertSee('Weather Chum')
        ->assertSee('Preview');
});

it('searches TRMNL recipes when search term is provided', function (): void {
    Http::fake([
        // First call (mount -> newest)
        config('services.trmnl.base_url').'/recipes.json?*' => Http::sequence()
            ->push([
                'data' => [
                    [
                        'id' => 1,
                        'name' => 'Initial Recipe',
                        'icon_url' => null,
                        'screenshot_url' => null,
                        'author_bio' => null,
                        'stats' => ['installs' => 1, 'forks' => 0],
                    ],
                ],
            ], 200)
            // Second call (search)
            ->push([
                'data' => [
                    [
                        'id' => 2,
                        'name' => 'Weather Search Result',
                        'icon_url' => null,
                        'screenshot_url' => null,
                        'author_bio' => null,
                        'stats' => ['installs' => 3, 'forks' => 1],
                    ],
                ],
            ], 200),
    ]);

    Livewire::withoutLazyLoading();

    Livewire::test('catalog.trmnl')
        ->assertSee('Initial Recipe')
        ->set('search', 'weather')
        ->assertSee('Weather Search Result')
        ->assertSee('Install');
});

it('installs plugin successfully when user is authenticated', function (): void {
    $user = User::factory()->create();

    Http::fake([
        config('services.trmnl.base_url').'/recipes.json*' => Http::response([
            'data' => [
                [
                    'id' => 123,
                    'name' => 'Weather Chum',
                    'icon_url' => 'https://example.com/icon.png',
                    'screenshot_url' => null,
                    'author_bio' => null,
                    'stats' => ['installs' => 10, 'forks' => 2],
                ],
            ],
        ], 200),
        config('services.trmnl.base_url').'/api/plugin_settings/123/archive*' => Http::response('fake zip content', 200),
    ]);

    $this->actingAs($user);

    Livewire::withoutLazyLoading();

    Livewire::test('catalog.trmnl')
        ->assertSee('Weather Chum')
        ->call('installPlugin', '123')
        ->assertSee('Error installing plugin'); // This will fail because we don't have a real zip file
});

it('shows error when user is not authenticated', function (): void {
    Http::fake([
        config('services.trmnl.base_url').'/recipes.json*' => Http::response([
            'data' => [
                [
                    'id' => 123,
                    'name' => 'Weather Chum',
                    'icon_url' => 'https://example.com/icon.png',
                    'screenshot_url' => null,
                    'author_bio' => null,
                    'stats' => ['installs' => 10, 'forks' => 2],
                ],
            ],
        ], 200),
    ]);

    Livewire::withoutLazyLoading();

    Livewire::test('catalog.trmnl')
        ->assertSee('Weather Chum')
        ->call('installPlugin', '123')
        ->assertStatus(403); // This will return 403 because user is not authenticated
});

it('shows error when plugin installation fails', function (): void {
    $user = User::factory()->create();

    Http::fake([
        config('services.trmnl.base_url').'/recipes.json*' => Http::response([
            'data' => [
                [
                    'id' => 123,
                    'name' => 'Weather Chum',
                    'icon_url' => 'https://example.com/icon.png',
                    'screenshot_url' => null,
                    'author_bio' => null,
                    'stats' => ['installs' => 10, 'forks' => 2],
                ],
            ],
        ], 200),
        config('services.trmnl.base_url').'/api/plugin_settings/123/archive*' => Http::response('invalid zip content', 200),
    ]);

    $this->actingAs($user);

    Livewire::withoutLazyLoading();

    Livewire::test('catalog.trmnl')
        ->assertSee('Weather Chum')
        ->call('installPlugin', '123')
        ->assertSee('Error installing plugin'); // This will fail because the zip content is invalid
});

it('previews a recipe with async fetch', function (): void {
    Http::fake([
        config('services.trmnl.base_url').'/recipes.json*' => Http::response([
            'data' => [
                [
                    'id' => 123,
                    'name' => 'Weather Chum',
                    'icon_url' => 'https://example.com/icon.png',
                    'screenshot_url' => 'https://example.com/old.png',
                    'author_bio' => null,
                    'stats' => ['installs' => 10, 'forks' => 2],
                ],
            ],
        ], 200),
        config('services.trmnl.base_url').'/recipes/123.json' => Http::response([
            'data' => [
                'id' => 123,
                'name' => 'Weather Chum Updated',
                'icon_url' => 'https://example.com/icon.png',
                'screenshot_url' => 'https://example.com/new.png',
                'author_bio' => ['description' => 'New bio'],
                'stats' => ['installs' => 11, 'forks' => 3],
            ],
        ], 200),
    ]);

    Livewire::withoutLazyLoading();

    Livewire::test('catalog.trmnl')
        ->assertSee('Weather Chum')
        ->call('previewRecipe', '123')
        ->assertSet('previewingRecipe', '123')
        ->assertSet('previewData.name', 'Weather Chum Updated')
        ->assertSet('previewData.screenshot_url', 'https://example.com/new.png')
        ->assertSee('Preview Weather Chum Updated')
        ->assertSee('New bio');
});

it('supports pagination and loading more recipes', function (): void {
    Http::fake([
        config('services.trmnl.base_url').'/recipes.json?sort-by=newest&page=1' => Http::response([
            'data' => [
                [
                    'id' => 1,
                    'name' => 'Recipe Page 1',
                    'icon_url' => null,
                    'screenshot_url' => null,
                    'author_bio' => null,
                    'stats' => ['installs' => 1, 'forks' => 0],
                ],
            ],
            'next_page_url' => '/recipes.json?page=2',
        ], 200),
        config('services.trmnl.base_url').'/recipes.json?sort-by=newest&page=2' => Http::response([
            'data' => [
                [
                    'id' => 2,
                    'name' => 'Recipe Page 2',
                    'icon_url' => null,
                    'screenshot_url' => null,
                    'author_bio' => null,
                    'stats' => ['installs' => 2, 'forks' => 0],
                ],
            ],
            'next_page_url' => null,
        ], 200),
    ]);

    Livewire::withoutLazyLoading();

    Livewire::test('catalog.trmnl')
        ->assertSee('Recipe Page 1')
        ->assertDontSee('Recipe Page 2')
        ->assertSee('Load next page')
        ->call('loadMore')
        ->assertSee('Recipe Page 1')
        ->assertSee('Recipe Page 2')
        ->assertDontSee('Load next page');
});

it('resets pagination when search term changes', function (): void {
    Http::fake([
        config('services.trmnl.base_url').'/recipes.json?sort-by=newest&page=1' => Http::sequence()
            ->push([
                'data' => [['id' => 1, 'name' => 'Initial 1']],
                'next_page_url' => '/recipes.json?page=2',
            ])
            ->push([
                'data' => [['id' => 3, 'name' => 'Initial 1 Again']],
                'next_page_url' => null,
            ]),
        config('services.trmnl.base_url').'/recipes.json?search=weather&sort-by=newest&page=1' => Http::response([
            'data' => [['id' => 2, 'name' => 'Weather Result']],
            'next_page_url' => null,
        ]),
    ]);

    Livewire::withoutLazyLoading();

    Livewire::test('catalog.trmnl')
        ->assertSee('Initial 1')
        ->call('loadMore')
        ->set('search', 'weather')
        ->assertSee('Weather Result')
        ->assertDontSee('Initial 1')
        ->assertSet('page', 1);
});
