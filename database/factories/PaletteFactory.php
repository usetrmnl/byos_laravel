<?php

namespace Database\Factories;

use App\Models\Palette;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Palette>
 */
class PaletteFactory extends Factory
{
    protected $model = Palette::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $colors = collect(range(1, 6))
            ->map(fn () => mb_strtoupper($this->faker->hexColor()))
            ->map(fn (string $hex) => mb_ltrim($hex, '#'))
            ->implode(',');

        return [
            'name' => $this->faker->unique()->slug(),
            'description' => $this->faker->sentence(),
            'palette' => $colors,
        ];
    }
}
