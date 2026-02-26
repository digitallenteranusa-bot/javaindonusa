<?php

namespace Database\Factories;

use App\Models\Odp;
use App\Models\Area;
use Illuminate\Database\Eloquent\Factories\Factory;

class OdpFactory extends Factory
{
    protected $model = Odp::class;

    public function definition(): array
    {
        return [
            'name' => 'ODP ' . $this->faker->numerify('##-##'),
            'code' => 'ODP-' . $this->faker->unique()->numerify('####'),
            'capacity' => $this->faker->randomElement([4, 8, 16]),
            'used_ports' => 0,
            'area_id' => Area::factory(),
            'is_active' => true,
            'latitude' => $this->faker->latitude(-8.5, -6.5),
            'longitude' => $this->faker->longitude(106, 112),
        ];
    }

    public function full(): static
    {
        return $this->state(fn (array $attributes) => [
            'used_ports' => $attributes['capacity'] ?? 8,
        ]);
    }
}
