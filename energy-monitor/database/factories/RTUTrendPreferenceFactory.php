<?php

namespace Database\Factories;

use App\Models\Gateway;
use App\Models\RTUTrendPreference;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RTUTrendPreference>
 */
class RTUTrendPreferenceFactory extends Factory
{
    protected $model = RTUTrendPreference::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'gateway_id' => Gateway::factory(),
            'selected_metrics' => $this->faker->randomElements(
                ['signal_strength', 'cpu_load', 'memory_usage', 'analog_input'],
                $this->faker->numberBetween(1, 3)
            ),
            'time_range' => $this->faker->randomElement(['1h', '6h', '24h', '7d']),
            'chart_type' => $this->faker->randomElement(['line', 'area', 'bar']),
        ];
    }

    /**
     * Create a preference with default settings.
     */
    public function withDefaults(): static
    {
        return $this->state(fn (array $attributes) => [
            'selected_metrics' => ['signal_strength'],
            'time_range' => '24h',
            'chart_type' => 'line',
        ]);
    }

    /**
     * Create a preference with multiple metrics.
     */
    public function withMultipleMetrics(): static
    {
        return $this->state(fn (array $attributes) => [
            'selected_metrics' => ['signal_strength', 'cpu_load', 'memory_usage'],
            'time_range' => '6h',
            'chart_type' => 'area',
        ]);
    }

    /**
     * Create a preference for a specific user.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }

    /**
     * Create a preference for a specific gateway.
     */
    public function forGateway(Gateway $gateway): static
    {
        return $this->state(fn (array $attributes) => [
            'gateway_id' => $gateway->id,
        ]);
    }
}