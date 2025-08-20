<?php

namespace Database\Factories;

use App\Models\Register;
use App\Models\Device;
use Illuminate\Database\Eloquent\Factories\Factory;

class RegisterFactory extends Factory
{
    protected $model = Register::class;

    public function definition(): array
    {
        return [
            'device_id' => Device::factory(),
            'parameter_name' => $this->faker->randomElement([
                'CPU Load',
                'Memory Usage',
                'Signal Strength',
                'Analog Voltage',
                'Temperature',
                'Power Consumption',
                'Frequency'
            ]),
            'register_address' => $this->faker->numberBetween(1, 65535),
            'data_type' => $this->faker->randomElement(['float', 'int', 'uint16', 'uint32']),
            'unit' => $this->faker->randomElement(['%', 'V', 'A', 'W', 'Hz', '°C', 'dBm']),
            'scale' => $this->faker->randomFloat(2, 0.1, 10),
            'normal_range' => $this->faker->randomElement(['0-100', '0-10', '-120--50', '0-1000']),
            'critical' => $this->faker->boolean(20), // 20% chance of being critical
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}