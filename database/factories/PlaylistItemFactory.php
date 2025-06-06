<?php

namespace Database\Factories;

use App\Models\Playlist;
use App\Models\PlaylistItem;
use App\Models\Plugin;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class PlaylistItemFactory extends Factory
{
    protected $model = PlaylistItem::class;

    public function definition(): array
    {
        return [
            'playlist_id' => Playlist::factory(),
            'plugin_id' => Plugin::factory(),
            'mashup' => null,
            'order' => $this->faker->numberBetween(0, 100),
            'is_active' => $this->faker->boolean(80), // 80% chance of being active
            'last_displayed_at' => null,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
