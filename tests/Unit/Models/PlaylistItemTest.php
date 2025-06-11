<?php

use App\Models\Playlist;
use App\Models\PlaylistItem;
use App\Models\Plugin;

test('playlist item belongs to playlist', function () {
    $playlist = Playlist::factory()->create();
    $playlistItem = PlaylistItem::factory()->create(['playlist_id' => $playlist->id]);

    expect($playlistItem->playlist)
        ->toBeInstanceOf(Playlist::class)
        ->id->toBe($playlist->id);
});

test('playlist item belongs to plugin', function () {
    $plugin = Plugin::factory()->create();
    $playlistItem = PlaylistItem::factory()->create(['plugin_id' => $plugin->id]);

    expect($playlistItem->plugin)
        ->toBeInstanceOf(Plugin::class)
        ->id->toBe($plugin->id);
});

test('playlist item can check if it is a mashup', function () {
    $plugin = Plugin::factory()->create();
    $regularItem = PlaylistItem::factory()->create([
        'mashup' => null,
        'plugin_id' => $plugin->id,
    ]);

    $plugin1 = Plugin::factory()->create();
    $plugin2 = Plugin::factory()->create();
    $mashupItem = PlaylistItem::factory()->create([
        'plugin_id' => $plugin1->id,
        'mashup' => [
            'mashup_layout' => '1Lx1R',
            'mashup_name' => 'Test Mashup',
            'plugin_ids' => [$plugin1->id, $plugin2->id],
        ],
    ]);

    expect($regularItem->isMashup())->toBeFalse()
        ->and($mashupItem->isMashup())->toBeTrue();
});

test('playlist item can get mashup name', function () {
    $plugin1 = Plugin::factory()->create();
    $plugin2 = Plugin::factory()->create();
    $mashupItem = PlaylistItem::factory()->create([
        'plugin_id' => $plugin1->id,
        'mashup' => [
            'mashup_layout' => '1Lx1R',
            'mashup_name' => 'Test Mashup',
            'plugin_ids' => [$plugin1->id, $plugin2->id],
        ],
    ]);

    expect($mashupItem->getMashupName())->toBe('Test Mashup');
});

test('playlist item can get mashup layout type', function () {
    $plugin1 = Plugin::factory()->create();
    $plugin2 = Plugin::factory()->create();
    $mashupItem = PlaylistItem::factory()->create([
        'plugin_id' => $plugin1->id,
        'mashup' => [
            'mashup_layout' => '1Lx1R',
            'mashup_name' => 'Test Mashup',
            'plugin_ids' => [$plugin1->id, $plugin2->id],
        ],
    ]);

    expect($mashupItem->getMashupLayoutType())->toBe('1Lx1R');
});

test('playlist item can get mashup plugin ids', function () {
    $plugin1 = Plugin::factory()->create();
    $plugin2 = Plugin::factory()->create();
    $mashupItem = PlaylistItem::factory()->create([
        'plugin_id' => $plugin1->id,
        'mashup' => [
            'mashup_layout' => '1Lx1R',
            'mashup_name' => 'Test Mashup',
            'plugin_ids' => [$plugin1->id, $plugin2->id],
        ],
    ]);

    expect($mashupItem->getMashupPluginIds())->toBe([$plugin1->id, $plugin2->id]);
});

test('playlist item can get required plugin count for different layouts', function () {
    $layouts = [
        '1Lx1R' => 2,
        '1Tx1B' => 2,
        '1Lx2R' => 3,
        '2Lx1R' => 3,
        '2Tx1B' => 3,
        '1Tx2B' => 3,
        '2x2' => 4,
    ];

    foreach ($layouts as $layout => $expectedCount) {
        $plugins = Plugin::factory()->count($expectedCount)->create();
        $pluginIds = $plugins->pluck('id')->toArray();

        $mashupItem = PlaylistItem::factory()->create([
            'plugin_id' => $pluginIds[0],
            'mashup' => [
                'mashup_layout' => $layout,
                'mashup_name' => 'Test Mashup',
                'plugin_ids' => $pluginIds,
            ],
        ]);

        expect($mashupItem->getRequiredPluginCount())->toBe($expectedCount);
    }
});

test('playlist item can get layout type', function () {
    $layoutTypes = [
        '1Lx1R' => 'vertical',
        '1Lx2R' => 'vertical',
        '2Lx1R' => 'vertical',
        '1Tx1B' => 'horizontal',
        '2Tx1B' => 'horizontal',
        '1Tx2B' => 'horizontal',
        '2x2' => 'grid',
    ];

    foreach ($layoutTypes as $layout => $expectedType) {
        $plugin1 = Plugin::factory()->create();
        $plugin2 = Plugin::factory()->create();
        $mashupItem = PlaylistItem::factory()->create([
            'plugin_id' => $plugin1->id,
            'mashup' => [
                'mashup_layout' => $layout,
                'mashup_name' => 'Test Mashup',
                'plugin_ids' => [$plugin1->id, $plugin2->id],
            ],
        ]);

        expect($mashupItem->getLayoutType())->toBe($expectedType);
    }
});

test('playlist item can get layout size for different positions', function () {
    $plugin1 = Plugin::factory()->create();
    $plugin2 = Plugin::factory()->create();
    $plugin3 = Plugin::factory()->create();

    $mashupItem = PlaylistItem::factory()->create([
        'plugin_id' => $plugin1->id,
        'mashup' => [
            'mashup_layout' => '2Lx1R',
            'mashup_name' => 'Test Mashup',
            'plugin_ids' => [$plugin1->id, $plugin2->id, $plugin3->id],
        ],
    ]);

    expect($mashupItem->getLayoutSize(0))->toBe('quadrant')
        ->and($mashupItem->getLayoutSize(1))->toBe('quadrant')
        ->and($mashupItem->getLayoutSize(2))->toBe('half_vertical');
});

test('playlist item can get available layouts', function () {
    $layouts = PlaylistItem::getAvailableLayouts();

    expect($layouts)->toBeArray()
        ->toHaveKeys(['1Lx1R', '1Lx2R', '2Lx1R', '1Tx1B', '2Tx1B', '1Tx2B', '2x2'])
        ->and($layouts['1Lx1R'])->toBe('1 Left - 1 Right (2 plugins)');
});

test('playlist item can get required plugin count for layout', function () {
    $layouts = [
        '1Lx1R' => 2,
        '1Tx1B' => 2,
        '1Lx2R' => 3,
        '2Lx1R' => 3,
        '2Tx1B' => 3,
        '1Tx2B' => 3,
        '2x2' => 4,
    ];

    foreach ($layouts as $layout => $expectedCount) {
        expect(PlaylistItem::getRequiredPluginCountForLayout($layout))->toBe($expectedCount);
    }
});

test('playlist item can create mashup', function () {
    $playlist = Playlist::factory()->create();
    $plugins = Plugin::factory()->count(3)->create();
    $pluginIds = $plugins->pluck('id')->toArray();
    $layout = '2Lx1R';
    $name = 'Test Mashup';
    $order = 1;

    $mashup = PlaylistItem::createMashup($playlist, $layout, $pluginIds, $name, $order);

    expect($mashup)
        ->toBeInstanceOf(PlaylistItem::class)
        ->playlist_id->toBe($playlist->id)
        ->plugin_id->toBe($pluginIds[0])
        ->mashup->toHaveKeys(['mashup_layout', 'mashup_name', 'plugin_ids'])
        ->mashup->mashup_layout->toBe($layout)
        ->mashup->mashup_name->toBe($name)
        ->mashup->plugin_ids->toBe($pluginIds)
        ->is_active->toBeTrue()
        ->order->toBe($order);
});
