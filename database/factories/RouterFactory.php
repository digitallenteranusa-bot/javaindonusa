<?php

namespace Database\Factories;

use App\Models\Router;
use Illuminate\Database\Eloquent\Factories\Factory;

class RouterFactory extends Factory
{
    protected $model = Router::class;

    public function definition(): array
    {
        return [
            'name' => 'Router ' . $this->faker->city(),
            'ip_address' => $this->faker->unique()->localIpv4(),
            'api_port' => 8728,
            'username' => 'admin',
            'password' => 'password',
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
