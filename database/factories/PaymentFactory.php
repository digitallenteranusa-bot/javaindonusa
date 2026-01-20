<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        $dateCode = now()->format('Ymd');

        return [
            'customer_id' => Customer::factory(),
            'payment_number' => 'PAY' . $dateCode . $this->faker->unique()->numerify('####'),
            'amount' => $this->faker->randomElement([100000, 150000, 200000, 250000, 300000]),
            'payment_method' => $this->faker->randomElement(['cash', 'transfer']),
            'payment_channel' => 'admin',
            'collector_id' => null,
            'received_by' => null,
            'status' => 'success',
            'notes' => $this->faker->optional()->sentence(),
        ];
    }

    public function cash(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => 'cash',
        ]);
    }

    public function transfer(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => 'transfer',
            'transfer_proof' => 'uploads/transfer_' . $this->faker->uuid() . '.jpg',
        ]);
    }

    public function byCollector(User $collector = null): static
    {
        return $this->state(fn (array $attributes) => [
            'collector_id' => $collector?->id ?? User::factory()->create(['role' => 'collector'])->id,
            'payment_channel' => 'collector',
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
        ]);
    }
}
