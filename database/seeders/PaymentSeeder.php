<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class PaymentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get paid and partial invoices
        $paidInvoices = Invoice::whereIn('status', ['paid', 'partial'])
            ->where('paid_amount', '>', 0)
            ->with('customer')
            ->get();

        $collectors = User::where('role', 'collector')->get();
        $admin = User::where('role', 'admin')->first();

        $paymentMethods = ['cash', 'cash', 'cash', 'transfer', 'transfer']; // 60% cash, 40% transfer
        $paymentChannels = ['collector', 'collector', 'collector', 'office', 'bank']; // Mostly collector

        foreach ($paidInvoices as $invoice) {
            $customer = $invoice->customer;
            $collector = $collectors->where('id', $customer->collector_id)->first() ?? $collectors->random();

            $method = $paymentMethods[array_rand($paymentMethods)];
            $channel = $method === 'transfer' ? ['bank', 'office'][array_rand(['bank', 'office'])] : $paymentChannels[array_rand($paymentChannels)];

            // Payment date is around due date
            $paymentDate = Carbon::parse($invoice->due_date)->subDays(rand(-5, 15));
            if ($paymentDate->gt(Carbon::now())) {
                $paymentDate = Carbon::now()->subDays(rand(1, 10));
            }

            $bankName = null;
            $bankAccount = null;
            $referenceNumber = null;

            if ($method === 'transfer') {
                $banks = ['BCA', 'Mandiri', 'BRI', 'BNI'];
                $bankName = $banks[array_rand($banks)];
                $bankAccount = rand(1000000000, 9999999999);
                $referenceNumber = strtoupper(substr(md5(rand()), 0, 12));
            }

            Payment::create([
                'payment_number' => 'PAY-' . $paymentDate->format('Ymd') . '-' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT),
                'customer_id' => $customer->id,
                'invoice_id' => $invoice->id,
                'collector_id' => $channel === 'collector' ? $collector->id : null,
                'amount' => $invoice->paid_amount,
                'payment_method' => $method,
                'payment_channel' => $channel,
                'bank_name' => $bankName,
                'bank_account' => $bankAccount,
                'reference_number' => $referenceNumber,
                'status' => 'verified',
                'verified_by' => $admin?->id,
                'verified_at' => $paymentDate,
                'allocated_invoices' => [
                    [
                        'invoice_id' => $invoice->id,
                        'amount' => $invoice->paid_amount,
                    ]
                ],
                'created_at' => $paymentDate,
                'updated_at' => $paymentDate,
            ]);
        }

        // Add some recent payments (last 30 days) for testing
        $recentCustomers = Customer::where('status', 'active')
            ->inRandomOrder()
            ->limit(30)
            ->get();

        foreach ($recentCustomers as $customer) {
            $collector = $collectors->where('id', $customer->collector_id)->first() ?? $collectors->random();
            $method = $paymentMethods[array_rand($paymentMethods)];
            $paymentDate = Carbon::now()->subDays(rand(1, 30));

            $amount = $customer->package->price * rand(1, 2);

            Payment::create([
                'payment_number' => Payment::generatePaymentNumber(),
                'customer_id' => $customer->id,
                'collector_id' => $collector->id,
                'amount' => $amount,
                'payment_method' => $method,
                'payment_channel' => 'collector',
                'status' => 'verified',
                'verified_by' => $admin?->id,
                'verified_at' => $paymentDate,
                'notes' => 'Pembayaran rutin',
                'created_at' => $paymentDate,
                'updated_at' => $paymentDate,
            ]);
        }
    }
}
