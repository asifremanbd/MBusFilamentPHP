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
            // RTU-specific fields with sensible defaults
            'gateway_type' => 'generic',
            'wan_ip' => $this->faker->ipv4,
            'sim_iccid' => $this->faker->numerify('894450##############'),
            'sim_apn' => $this->faker->domainName,
            'sim_operator' => $this->faker->company,
            'cpu_load' => $this->faker->randomFloat(2, 0, 100),
            'memory_usage' => $this->faker->randomFloat(2, 0, 100),
            'uptime_hours' => $this->faker->numberBetween(0, 8760), // Up to 1 year
            'rssi' => $this->faker->numberBetween(-120, -50),
            'rsrp' => $this->faker->numberBetween(-140, -44),
            'rsrq' => $this->faker->numberBetween(-20, -3),
            'sinr' => $this->faker->numberBetween(-20, 30),
            'di1_status' => $this->faker->boolean,
            'di2_status' => $this->faker->boolean,
            'do1_status' => $this->faker->boolean,
            'do2_status' => $this->faker->boolean,
            'analog_input_voltage' => $this->faker->randomFloat(3, 0, 10),
            'last_system_update' => $this->faker->dateTimeBetween('-1 hour', 'now'),
            'communication_status' => $this->faker->randomElement(['online', 'warning', 'offline']),
        ];
    }

    /**
     * Create a Teltonika RUT956 RTU gateway
     */
    public function rtu(): static
    {
        return $this->state(fn (array $attributes) => [
            'gateway_type' => 'teltonika_rut956',
            'name' => $this->faker->company . ' RTU Gateway',
        ]);
    }

    /**
     * Create a gateway with optimal health conditions
     */
    public function healthy(): static
    {
        return $this->state(fn (array $attributes) => [
            'cpu_load' => $this->faker->randomFloat(2, 10, 70),
            'memory_usage' => $this->faker->randomFloat(2, 20, 80),
            'communication_status' => 'online',
            'rssi' => $this->faker->numberBetween(-80, -50),
        ]);
    }

    /**
     * Create a gateway with poor health conditions
     */
    public function unhealthy(): static
    {
        return $this->state(fn (array $attributes) => [
            'cpu_load' => $this->faker->randomFloat(2, 85, 100),
            'memory_usage' => $this->faker->randomFloat(2, 90, 100),
            'communication_status' => 'offline',
            'rssi' => $this->faker->numberBetween(-120, -100),
        ]);
    }
}
