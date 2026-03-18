<?php

namespace Database\Factories;

use App\Enums\DeviceSensorKind;
use App\Enums\DeviceSensorSource;
use App\Models\DeviceSensor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeviceSensor>
 */
class DeviceSensorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'device_id' => null,
            'make' => 'Sensirion',
            'model' => 'SCD41',
            'kind' => DeviceSensorKind::TEMPERATURE,
            'value' => $this->faker->randomFloat(2, -10, 40),
            'unit' => 'celcius',
            'source' => DeviceSensorSource::DEVICE,
        ];
    }
}
