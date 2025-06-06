<?php

namespace Database\Seeders;

use App\Models\Playlist;
use App\Models\PlaylistItem;
use Illuminate\Database\Seeder;

class MashupPocSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create a playlist
        $playlist = Playlist::create([
            'device_id' => 1,
            'name' => 'Mashup Test Playlist',
            'is_active' => true,
        ]);

        // Create a playlist item with 1Tx1B layout using the new JSON structure
        PlaylistItem::createMashup(
            playlist: $playlist,
            layout: '1Tx1B',
            pluginIds: [2, 3], // Top and bottom plugins
            name: 'Mashup 1Tx1B',
            order: 1
        );

        // Create another playlist item with 2x2 layout
        PlaylistItem::createMashup(
            playlist: $playlist,
            layout: '1Lx1R',
            pluginIds: [2, 6], // All four quadrants
            name: 'Mashup Quadrant',
            order: 2
        );

        // Create a single plugin item (no mashup)
        PlaylistItem::create([
            'playlist_id' => $playlist->id,
            'plugin_id' => 1,
            'mashup' => null,
            'is_active' => true,
            'order' => 3,
        ]);
    }
}
