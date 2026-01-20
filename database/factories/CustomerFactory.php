<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Package;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'customer_id' => 'CUST' . $this->faker->unique()->numerify('####'),
            'name' => $this->faker->name(),
            'phone' => '08' . $this->faker->numerify('##########'),
            'email' => $this->faker->unique()->safeEmail(),
            'address' => $this->faker->address(),
            'package_id' => Package::factory(),
            'status' => 'active',
            'total_debt' => 0,
            'connection_type' => $this->faker->randomElement(['pppoe', 'static']),
            'pppoe_username' => $this->faker->userName(),
            'payment_behavior' => 'regular',
            'registration_date' => $this->faker->dateTimeBetween('-2 years', '-1 month'),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
        ]);
    }

    public function isolated(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'isolated',
        ]);
    }

    public function withDebt(int $amount): static
    {
        return $this->state(fn (array $attributes) => [
            'total_debt' => $amount,
        ]);
    }
}
