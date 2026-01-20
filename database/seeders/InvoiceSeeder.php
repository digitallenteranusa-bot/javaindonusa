<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class InvoiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $customers = Customer::with('package')->get();
        $currentDate = Carbon::now();
        $currentMonth = $currentDate->month;
        $currentYear = $currentDate->year;

        foreach ($customers as $customer) {
            $joinDate = Carbon::parse($customer->join_date);
            $package = $customer->package;

            // Generate invoices from join date until current month
            $startMonth = $joinDate->copy()->startOfMonth();
            $endMonth = $currentDate->copy()->startOfMonth();

            $period = $startMonth->copy();
            $remainingDebt = $customer->total_debt;

            while ($period->lte($endMonth)) {
                $month = $period->month;
                $year = $period->year;

                // Determine invoice status based on customer status and debt
                $dueDate = Carbon::create($year, $month, 20);
                $isPastDue = $dueDate->lt($currentDate);

                // Calculate amounts
                $totalAmount = $package->price;
                $paidAmount = 0;
                $status = 'pending';

                if ($remainingDebt > 0 && $isPastDue) {
                    // This is an unpaid/partial invoice
                    if ($remainingDebt >= $totalAmount) {
                        // Fully unpaid
                        $paidAmount = 0;
                        $status = $isPastDue ? 'overdue' : 'pending';
                        $remainingDebt -= $totalAmount;
                    } else {
                        // Partially paid
                        $paidAmount = $totalAmount - $remainingDebt;
                        $status = 'partial';
                        $remainingDebt = 0;
                    }
                } else {
                    // Paid invoice
                    $paidAmount = $totalAmount;
                    $status = 'paid';
                }

                // For current month, make it pending
                if ($month === $currentMonth && $year === $currentYear) {
                    if ($customer->total_debt > 0) {
                        $status = 'pending';
                        $paidAmount = 0;
                    }
                }

                $invoiceNumber = Invoice::generateInvoiceNumber($customer->id, $month, $year);

                Invoice::create([
                    'invoice_number' => $invoiceNumber,
                    'customer_id' => $customer->id,
                    'period_month' => $month,
                    'period_year' => $year,
                    'package_id' => $package->id,
                    'package_name' => $package->name,
                    'package_price' => $package->price,
                    'additional_charges' => 0,
                    'discount' => 0,
                    'total_amount' => $totalAmount,
                    'paid_amount' => $paidAmount,
                    'remaining_amount' => $totalAmount - $paidAmount,
                    'status' => $status,
                    'due_date' => $dueDate,
                    'paid_at' => $status === 'paid' ? $dueDate->copy()->subDays(rand(1, 15)) : null,
                    'generated_at' => Carbon::create($year, $month, 1, 0, 0, 0),
                ]);

                $period->addMonth();
            }
        }
    }
}
