<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Gateway>
 */
class GatewayFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->company . ' Gateway',
            'fixed_ip' => $this->faker->localIpv4,
            'sim_number' => $this->faker->phoneNumber,
            'gsm_signal' => $this->faker->numberBetween(-120, -50),
            'gnss_location' => $this->faker->latitude . ', ' . $this->faker->longitude,
        ];
    }
}
