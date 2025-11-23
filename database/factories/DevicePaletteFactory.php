<?php

namespace Database\Factories;

use App\Models\DevicePalette;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DevicePalette>
 */
class DevicePaletteFactory extends Factory
{
    protected $model = DevicePalette::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => 'test-' . $this->faker->unique()->slug(),
            'name' => $this->faker->words(3, true),
            'grays' => $this->faker->randomElement([2, 4, 16, 256]),
            'colors' => $this->faker->optional()->passthrough([
                '#FF0000',
                '#00FF00',
                '#0000FF',
                '#FFFF00',
                '#000000',
                '#FFFFFF',
            ]),
            'framework_class' => null,
            'source' => 'api',
        ];
    }
}
