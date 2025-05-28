<?php

namespace Database\Factories;

use App\Models\Firmware;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class FirmwareFactory extends Factory
{
    protected $model = Firmware::class;

    public function definition(): array
    {
        return [
            'version_tag' => $this->faker->word(),
            'url' => $this->faker->url(),
            'latest' => $this->faker->boolean(),
            'storage_location' => $this->faker->word(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
