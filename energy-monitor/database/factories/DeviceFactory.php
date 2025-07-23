<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Device>
 */
class DeviceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(2, true) . ' Device',
            'slave_id' => $this->faker->numberBetween(1, 247),
            'location_tag' => $this->faker->streetAddress,
            'gateway_id' => \App\Models\Gateway::factory(),
        ];
    }
}
