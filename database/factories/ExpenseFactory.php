<?php

namespace Database\Factories;

use App\Models\Expense;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->collector(),
            'expense_number' => 'EXP' . $this->faker->unique()->numerify('########'),
            'amount' => $this->faker->randomElement([10000, 20000, 30000, 50000]),
            'category' => $this->faker->randomElement(['transport', 'meal', 'parking', 'fuel']),
            'description' => $this->faker->sentence(),
            'status' => 'pending',
            'expense_date' => now(),
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
            'verified_by' => User::factory()->admin(),
            'verified_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'rejected',
            'verified_by' => User::factory()->admin(),
            'verified_at' => now(),
            'rejection_reason' => 'Tidak sesuai kebijakan',
        ]);
    }
}
