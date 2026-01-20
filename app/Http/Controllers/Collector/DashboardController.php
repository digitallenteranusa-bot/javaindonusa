<?php

namespace App\Http\Controllers\Collector;

use App\Http\Controllers\Controller;
use App\Services\Collector\CollectorService;
use App\Services\Collector\ExpenseService;
use App\Models\Customer;
use App\Models\CollectionLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class DashboardController extends Controller
{
    protected CollectorService $collectorService;
    protected ExpenseService $expenseService;

    public function __construct(
        CollectorService $collectorService,
        ExpenseService $expenseService
    ) {
        $this->collectorService = $collectorService;
        $this->expenseService = $expenseService;
    }

    /**
     * Dashboard utama penagih
     */
    public function index(Request $request)
    {
        $collector = auth()->user();
        $period = $request->get('period', 'today');

        // Ambil statistik
        $stats = $this->collectorService->getDashboardStats($collector, $period);

        // Ambil pelanggan menunggak
        $overdueCustomers = $this->collectorService->getOverdueCustomers(
            $collector,
            $request->get('search'),
            $request->get('status'),
            10
        );

        // Ringkasan harian
        $dailySummary = $this->collectorService->getDailySummary($collector);

        return Inertia::render('Collector/Dashboard', [
            'stats' => $stats,
            'overdueCustomers' => $overdueCustomers,
            'dailySummary' => $dailySummary,
            'filters' => [
                'search' => $request->get('search'),
                'status' => $request->get('status'),
                'period' => $period,
            ],
        ]);
    }

    /**
     * Daftar pelanggan yang ditugaskan
     */
    public function customers(Request $request)
    {
        $collector = auth()->user();

        $customers = $this->collectorService->getOverdueCustomers(
            $collector,
            $request->get('search'),
            $request->get('status'),
            20
        );

        return Inertia::render('Collector/Customers', [
            'customers' => $customers,
            'filters' => [
                'search' => $request->get('search'),
                'status' => $request->get('status'),
            ],
        ]);
    }

    /**
     * Detail pelanggan
     */
    public function customerDetail(Customer $customer)
    {
        $collector = auth()->user();

        // Validasi akses
        if ($customer->collector_id !== $collector->id) {
            abort(403, 'Anda tidak memiliki akses ke pelanggan ini');
        }

        $customer->load([
            'package',
            'area',
            'invoices' => function ($q) {
                $q->orderBy('period_year', 'desc')
                    ->orderBy('period_month', 'desc')
                    ->limit(12);
            },
            'payments' => function ($q) {
                $q->orderBy('created_at', 'desc')->limit(10);
            },
        ]);

        // Histori kunjungan
        $visitHistory = CollectionLog::where('customer_id', $customer->id)
            ->where('collector_id', $collector->id)
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        return Inertia::render('Collector/CustomerDetail', [
            'customer' => $customer,
            'visitHistory' => $visitHistory,
        ]);
    }

    /**
     * Proses pembayaran tunai
     */
    public function processCashPayment(Request $request, Customer $customer)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1000',
            'notes' => 'nullable|string|max:500',
        ]);

        $collector = auth()->user();

        try {
            $result = $this->collectorService->processCashPayment(
                $collector,
                $customer,
                $request->amount,
                $request->notes
            );

            return back()->with('success', [
                'message' => 'Pembayaran berhasil dicatat',
                'payment' => $result['payment'],
                'access_opened' => $result['access_opened'],
            ]);

        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Proses pembayaran transfer
     */
    public function processTransferPayment(Request $request, Customer $customer)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1000',
            'transfer_proof' => 'required|image|max:5120', // Max 5MB
            'notes' => 'nullable|string|max:500',
        ]);

        $collector = auth()->user();

        try {
            // Upload bukti transfer
            $proofPath = $this->expenseService->uploadReceipt(
                $collector,
                $request->file('transfer_proof')
            );

            $result = $this->collectorService->processTransferPayment(
                $collector,
                $customer,
                $request->amount,
                $proofPath,
                $request->notes
            );

            return back()->with('success', [
                'message' => 'Pembayaran transfer berhasil dicatat',
                'payment' => $result['payment'],
                'access_opened' => $result['access_opened'],
            ]);

        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Catat kunjungan
     */
    public function logVisit(Request $request, Customer $customer)
    {
        $request->validate([
            'action_type' => 'required|in:visit,not_home,refused,promise_to_pay,rescheduled',
            'notes' => 'nullable|string|max:500',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        $collector = auth()->user();

        try {
            $this->collectorService->logVisit(
                $collector,
                $customer,
                $request->action_type,
                $request->notes,
                $request->latitude,
                $request->longitude
            );

            return back()->with('success', 'Kunjungan berhasil dicatat');

        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Kirim reminder WhatsApp
     */
    public function sendWhatsAppReminder(Customer $customer)
    {
        $collector = auth()->user();

        if ($customer->collector_id !== $collector->id) {
            return back()->with('error', 'Anda tidak memiliki akses ke pelanggan ini');
        }

        // Generate WhatsApp URL
        $message = $this->generateReminderMessage($customer);
        $phone = preg_replace('/[^0-9]/', '', $customer->phone);

        // Ubah 08 ke 628
        if (str_starts_with($phone, '0')) {
            $phone = '62' . substr($phone, 1);
        }

        $whatsappUrl = "https://wa.me/{$phone}?text=" . urlencode($message);

        // Log aktivitas
        CollectionLog::create([
            'collector_id' => $collector->id,
            'customer_id' => $customer->id,
            'action_type' => 'reminder_sent',
            'notes' => 'WhatsApp reminder sent',
        ]);

        return response()->json([
            'success' => true,
            'whatsapp_url' => $whatsappUrl,
        ]);
    }

    /**
     * Generate pesan reminder
     */
    protected function generateReminderMessage(Customer $customer): string
    {
        $ispInfo = \App\Models\IspInfo::first();

        return "Yth. Bapak/Ibu {$customer->name},\n\n" .
            "Kami dari {$ispInfo->company_name} mengingatkan bahwa tagihan internet Anda sebesar " .
            "Rp " . number_format($customer->total_debt, 0, ',', '.') . " belum terbayar.\n\n" .
            "Mohon segera melakukan pembayaran untuk menghindari pemutusan layanan.\n\n" .
            "Informasi pembayaran:\n" .
            "- Transfer ke: " . $this->formatBankAccounts($ispInfo->bank_accounts) . "\n" .
            "- Atau hubungi: {$ispInfo->phone_primary}\n\n" .
            "Terima kasih.";
    }

    protected function formatBankAccounts($accounts): string
    {
        if (is_string($accounts)) {
            $accounts = json_decode($accounts, true);
        }

        return collect($accounts)->map(function ($acc) {
            return "{$acc['bank']} {$acc['account']} a.n {$acc['name']}";
        })->join(' / ');
    }
}
