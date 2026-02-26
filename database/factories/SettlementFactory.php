<?php

namespace Database\Factories;

use App\Models\Settlement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SettlementFactory extends Factory
{
    protected $model = Settlement::class;

    public function definition(): array
    {
        $cashCollection = $this->faker->randomElement([500000, 1000000, 1500000]);
        $expense = $this->faker->randomElement([50000, 100000]);
        $expected = $cashCollection - $expense;

        return [
            'collector_id' => User::factory()->collector(),
            'settlement_number' => 'STL-' . now()->format('Ymd') . '-' . $this->faker->unique()->numerify('#####'),
            'settlement_date' => now(),
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'total_collection' => $cashCollection,
            'cash_collection' => $cashCollection,
            'total_expense' => $expense,
            'approved_expense' => $expense,
            'expected_amount' => $expected,
            'actual_amount' => $expected,
            'difference' => 0,
            'status' => 'pending',
        ];
    }

    public function settled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'settled',
            'received_by' => User::factory()->admin(),
            'verified_at' => now(),
        ]);
    }

    public function discrepancy(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'discrepancy',
            'actual_amount' => ($attributes['expected_amount'] ?? 500000) - 50000,
            'difference' => -50000,
        ]);
    }
}
