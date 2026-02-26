<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    protected static int $monthSequence = 0;

    public function definition(): array
    {
        // Use a sequence to avoid unique constraint collisions (customer_id + period_month + period_year)
        $seq = static::$monthSequence++;
        $month = ($seq % 12) + 1;
        $year = 2023 + intdiv($seq, 12);
        $amount = $this->faker->randomElement([100000, 150000, 200000, 250000, 300000]);

        return [
            'customer_id' => Customer::factory(),
            'invoice_number' => 'INV' . $year . str_pad($month, 2, '0', STR_PAD_LEFT) . $this->faker->unique()->numerify('####'),
            'period_month' => $month,
            'period_year' => $year,
            'package_name' => 'Paket Internet',
            'package_price' => $amount,
            'additional_charges' => 0,
            'discount' => 0,
            'total_amount' => $amount,
            'paid_amount' => 0,
            'remaining_amount' => $amount,
            'due_date' => Carbon::create($year, $month, 20),
            'status' => 'pending',
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'paid_amount' => 0,
            'remaining_amount' => $attributes['total_amount'],
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'paid',
            'paid_amount' => $attributes['total_amount'],
            'remaining_amount' => 0,
            'paid_at' => now(),
        ]);
    }

    public function partial(int $paidAmount = null): static
    {
        return $this->state(function (array $attributes) use ($paidAmount) {
            $paid = $paidAmount ?? ($attributes['total_amount'] / 2);
            return [
                'status' => 'partial',
                'paid_amount' => $paid,
                'remaining_amount' => $attributes['total_amount'] - $paid,
            ];
        });
    }

    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'overdue',
            'due_date' => now()->subDays(30),
        ]);
    }
}
