<?php

namespace Database\Factories;

use App\Models\Reading;
use App\Models\Device;
use App\Models\Register;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReadingFactory extends Factory
{
    protected $model = Reading::class;

    public function definition(): array
    {
        return [
            'device_id' => Device::factory(),
            'register_id' => Register::factory(),
            'value' => $this->faker->randomFloat(4, 0, 1000),
            'timestamp' => $this->faker->dateTimeBetween('-1 week', 'now'),
        ];
    }
}