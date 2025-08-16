<?php

namespace Database\Factories;

use App\Models\DeviceModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DeviceModel>
 */
class DeviceModelFactory extends Factory
{
    protected $model = DeviceModel::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->slug(),
            'label' => $this->faker->words(2, true),
            'description' => $this->faker->sentence(),
            'width' => $this->faker->randomElement([800, 1024, 1280, 1920]),
            'height' => $this->faker->randomElement([480, 600, 720, 1080]),
            'colors' => $this->faker->randomElement([2, 16, 256, 65536]),
            'bit_depth' => $this->faker->randomElement([1, 4, 8, 16]),
            'scale_factor' => $this->faker->randomElement([1, 2, 4]),
            'rotation' => $this->faker->randomElement([0, 90, 180, 270]),
            'mime_type' => $this->faker->randomElement(['image/png', 'image/jpeg', 'image/gif']),
            'offset_x' => $this->faker->numberBetween(-100, 100),
            'offset_y' => $this->faker->numberBetween(-100, 100),
            'published_at' => $this->faker->optional()->dateTimeBetween('-1 year', 'now'),
        ];
    }
}
