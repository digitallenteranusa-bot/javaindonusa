<?php

namespace Database\Factories;

use App\Models\Package;
use Illuminate\Database\Eloquent\Factories\Factory;

class PackageFactory extends Factory
{
    protected $model = Package::class;

    public function definition(): array
    {
        $speedMap = [5 => 5120, 10 => 10240, 20 => 20480, 50 => 51200, 100 => 102400];
        $speedMbps = $this->faker->randomElement(array_keys($speedMap));
        $speedKbps = $speedMap[$speedMbps];

        return [
            'name' => 'Paket ' . $speedMbps . ' Mbps',
            'code' => 'PKT' . $this->faker->unique()->numerify('###'),
            'speed_download' => $speedKbps,
            'speed_upload' => intval($speedKbps / 2),
            'price' => $this->faker->randomElement([100000, 150000, 200000, 300000, 500000]),
            'description' => $this->faker->sentence(),
            'is_active' => true,
            'pppoe_pool' => 'broadband',
        ];
    }
}
