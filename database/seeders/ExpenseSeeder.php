<?php

namespace Database\Seeders;

use App\Models\Expense;
use App\Models\User;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class ExpenseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $collectors = User::where('role', 'penagih')->get();
        $admin = User::where('role', 'admin')->first();

        $categories = [
            'transport' => ['Ojek ke lokasi', 'Angkot PP', 'Grab/Gojek', 'Bensin motor'],
            'meal' => ['Makan siang', 'Makan pagi', 'Snack dan minum'],
            'parking' => ['Parkir motor', 'Parkir mall', 'Parkir pasar'],
            'fuel' => ['BBM Pertamax', 'BBM Pertalite', 'Isi bensin full'],
            'toll' => ['Tol dalam kota', 'Tol lingkar luar'],
            'other' => ['Pulsa darurat', 'Fotokopi kwitansi', 'Beli map/amplop'],
        ];

        $categoryAmounts = [
            'transport' => [10000, 15000, 20000, 25000, 30000],
            'meal' => [15000, 20000, 25000, 30000, 35000],
            'parking' => [2000, 3000, 5000, 10000],
            'fuel' => [20000, 30000, 50000, 75000, 100000],
            'toll' => [10000, 15000, 20000, 25000],
            'other' => [5000, 10000, 15000, 20000],
        ];

        // Generate expenses for last 60 days
        for ($day = 60; $day >= 0; $day--) {
            $date = Carbon::now()->subDays($day);

            // Skip Sundays
            if ($date->isSunday()) {
                continue;
            }

            foreach ($collectors as $collector) {
                // Each collector has 2-5 expenses per day
                $numExpenses = rand(2, 5);

                for ($i = 0; $i < $numExpenses; $i++) {
                    $category = array_rand($categories);
                    $descriptions = $categories[$category];
                    $amounts = $categoryAmounts[$category];

                    $description = $descriptions[array_rand($descriptions)];
                    $amount = $amounts[array_rand($amounts)];

                    // Determine status - older ones are mostly approved
                    $status = 'pending';
                    if ($day > 7) {
                        $status = rand(1, 100) <= 90 ? 'approved' : 'rejected';
                    } elseif ($day > 2) {
                        $status = rand(1, 100) <= 60 ? 'approved' : 'pending';
                    }

                    $verifiedAt = null;
                    $verifiedBy = null;
                    $rejectionReason = null;

                    if ($status !== 'pending') {
                        $verifiedAt = $date->copy()->addHours(rand(1, 48));
                        $verifiedBy = $admin?->id;

                        if ($status === 'rejected') {
                            $rejectionReasons = [
                                'Nota tidak jelas',
                                'Melebihi batas harian',
                                'Kategori tidak sesuai',
                                'Duplikat pengeluaran',
                            ];
                            $rejectionReason = $rejectionReasons[array_rand($rejectionReasons)];
                        }
                    }

                    Expense::create([
                        'user_id' => $collector->id,
                        'expense_number' => 'EXP-' . $date->format('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT),
                        'amount' => $amount,
                        'category' => $category,
                        'description' => $description,
                        'status' => $status,
                        'expense_date' => $date->toDateString(),
                        'verified_by' => $verifiedBy,
                        'verified_at' => $verifiedAt,
                        'rejection_reason' => $rejectionReason,
                        'created_at' => $date->copy()->setTime(rand(8, 17), rand(0, 59)),
                        'updated_at' => $date->copy()->setTime(rand(8, 17), rand(0, 59)),
                    ]);
                }
            }
        }
    }
}
