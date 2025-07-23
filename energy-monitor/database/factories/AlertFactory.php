<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Alert>
 */
class AlertFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'device_id' => \App\Models\Device::factory(),
            'parameter_name' => $this->faker->randomElement(['voltage', 'current', 'power', 'temperature', 'frequency']),
            'value' => $this->faker->randomFloat(2, 0, 1000),
            'severity' => $this->faker->randomElement(['info', 'warning', 'critical']),
            'timestamp' => $this->faker->dateTimeBetween('-1 week', 'now'),
            'resolved' => $this->faker->boolean(30), // 30% chance of being resolved
            'resolved_by' => null,
            'resolved_at' => null,
            'message' => $this->faker->sentence,
        ];
    }
}
