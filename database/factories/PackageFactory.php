<?php

namespace Database\Factories;

use App\Models\Package;
use Illuminate\Database\Eloquent\Factories\Factory;

class PackageFactory extends Factory
{
    protected $model = Package::class;

    public function definition(): array
    {
        $speeds = ['5 Mbps', '10 Mbps', '20 Mbps', '50 Mbps', '100 Mbps'];
        $speed = $this->faker->randomElement($speeds);

        return [
            'name' => 'Paket ' . $speed,
            'speed' => $speed,
            'price' => $this->faker->randomElement([100000, 150000, 200000, 300000, 500000]),
            'description' => $this->faker->sentence(),
            'is_active' => true,
        ];
    }
}
