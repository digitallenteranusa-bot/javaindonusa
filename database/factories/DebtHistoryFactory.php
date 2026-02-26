<?php

namespace Database\Factories;

use App\Models\DebtHistory;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

class DebtHistoryFactory extends Factory
{
    protected $model = DebtHistory::class;

    public function definition(): array
    {
        $amount = $this->faker->randomElement([100000, 150000, 200000, 300000]);

        return [
            'customer_id' => Customer::factory(),
            'type' => DebtHistory::TYPE_CHARGE,
            'amount' => $amount,
            'balance_before' => 0,
            'balance_after' => $amount,
            'description' => 'Tagihan baru',
        ];
    }

    public function charge(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => DebtHistory::TYPE_CHARGE,
        ]);
    }

    public function payment(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => DebtHistory::TYPE_PAYMENT,
        ]);
    }

    public function adjustment(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => DebtHistory::TYPE_ADJUSTMENT_ADD,
        ]);
    }
}
