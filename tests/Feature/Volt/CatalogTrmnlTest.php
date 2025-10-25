<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;

it('loads newest TRMNL recipes on mount', function () {
    Http::fake([
        'usetrmnl.com/recipes.json*' => Http::response([
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

    Volt::test('catalog.trmnl')
        ->assertSee('Weather Chum')
        ->assertSee('Install')
        ->assertSee('Installs: 10');
});

it('searches TRMNL recipes when search term is provided', function () {
    Http::fake([
        // First call (mount -> newest)
        'usetrmnl.com/recipes.json?*' => Http::sequence()
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

    Volt::test('catalog.trmnl')
        ->assertSee('Initial Recipe')
        ->set('search', 'weather')
        ->assertSee('Weather Search Result')
        ->assertSee('Install');
});
