<?php

namespace Database\Seeders;

use App\Models\CollectionLog;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class CollectionLogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $collectors = User::where('role', 'penagih')->get();
        $customersWithDebt = Customer::where('total_debt', '>', 0)->get();

        $actionTypes = [
            'visit' => 20,           // 20% - just visited
            'payment_cash' => 25,    // 25% - cash payment
            'payment_transfer' => 15, // 15% - transfer payment
            'not_home' => 20,        // 20% - customer not home
            'refused' => 5,          // 5% - refused to pay
            'promise_to_pay' => 10,  // 10% - promised to pay
            'reminder_sent' => 5,    // 5% - WhatsApp reminder
        ];

        // Generate collection logs for last 30 days
        for ($day = 30; $day >= 0; $day--) {
            $date = Carbon::now()->subDays($day);

            // Skip Sundays
            if ($date->isSunday()) {
                continue;
            }

            foreach ($collectors as $collector) {
                // Get customers assigned to this collector
                $collectorCustomers = $customersWithDebt->where('collector_id', $collector->id);

                if ($collectorCustomers->isEmpty()) {
                    continue;
                }

                // Each collector visits 5-15 customers per day
                $numVisits = rand(5, min(15, $collectorCustomers->count()));
                $visitedCustomers = $collectorCustomers->random(min($numVisits, $collectorCustomers->count()));

                foreach ($visitedCustomers as $customer) {
                    // Randomly select action type based on weights
                    $actionType = $this->weightedRandom($actionTypes);

                    $amount = null;
                    $paymentMethod = null;
                    $paymentId = null;

                    // Handle payment actions
                    if (in_array($actionType, ['payment_cash', 'payment_transfer'])) {
                        $amount = $customer->package->price * rand(1, 3);
                        $paymentMethod = $actionType === 'payment_cash' ? 'cash' : 'transfer';

                        // Link to existing payment if available
                        $payment = Payment::where('customer_id', $customer->id)
                            ->whereDate('created_at', $date)
                            ->first();
                        $paymentId = $payment?->id;
                    }

                    // Generate realistic GPS coordinates near customer location
                    $latitude = $customer->latitude ? $customer->latitude + (rand(-10, 10) / 100000) : null;
                    $longitude = $customer->longitude ? $customer->longitude + (rand(-10, 10) / 100000) : null;

                    // Random visit time during working hours
                    $visitTime = $date->copy()->setTime(rand(8, 17), rand(0, 59), rand(0, 59));

                    $notes = $this->generateNotes($actionType, $customer->name);

                    CollectionLog::create([
                        'collector_id' => $collector->id,
                        'customer_id' => $customer->id,
                        'payment_id' => $paymentId,
                        'action_type' => $actionType,
                        'amount' => $amount,
                        'payment_method' => $paymentMethod,
                        'visit_time' => $visitTime,
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                        'notes' => $notes,
                        'device_info' => [
                            'platform' => 'Android',
                            'version' => '1.0.' . rand(1, 10),
                            'device' => ['Samsung', 'Xiaomi', 'Oppo', 'Vivo', 'Realme'][array_rand(['Samsung', 'Xiaomi', 'Oppo', 'Vivo', 'Realme'])],
                        ],
                        'created_at' => $visitTime,
                        'updated_at' => $visitTime,
                    ]);
                }
            }
        }
    }

    /**
     * Weighted random selection
     */
    private function weightedRandom(array $weights): string
    {
        $total = array_sum($weights);
        $rand = rand(1, $total);

        $current = 0;
        foreach ($weights as $key => $weight) {
            $current += $weight;
            if ($rand <= $current) {
                return $key;
            }
        }

        return array_key_first($weights);
    }

    /**
     * Generate notes based on action type
     */
    private function generateNotes(string $actionType, string $customerName): ?string
    {
        $notes = [
            'visit' => [
                'Kunjungan rutin',
                'Konfirmasi tagihan',
                'Cek status pembayaran',
                null,
            ],
            'payment_cash' => [
                'Pembayaran diterima tunai',
                'Bayar tunai di tempat',
                null,
            ],
            'payment_transfer' => [
                'Bukti transfer sudah diterima',
                'Transfer via mobile banking',
                null,
            ],
            'not_home' => [
                'Tidak ada orang di rumah',
                'Rumah tutup',
                'Tetangga bilang sedang kerja',
                'Akan dikunjungi lagi besok',
            ],
            'refused' => [
                'Pelanggan menolak bayar',
                'Komplain kualitas jaringan',
                'Minta diskon dulu',
            ],
            'promise_to_pay' => [
                'Janji bayar minggu depan',
                'Akan transfer hari Jumat',
                'Nunggu gajian tanggal 25',
                'Janji bayar sebelum tanggal 20',
            ],
            'reminder_sent' => [
                'WhatsApp reminder terkirim',
                'Reminder tagihan via WA',
                null,
            ],
        ];

        $options = $notes[$actionType] ?? [null];
        return $options[array_rand($options)];
    }
}
