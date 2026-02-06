<?php

namespace App\Http\Controllers\Collector;

use App\Http\Controllers\Controller;
use App\Services\Collector\CollectorService;
use App\Services\Collector\ExpenseService;
use App\Models\Customer;
use App\Models\CollectionLog;
use App\Models\Odp;
use App\Models\Area;
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

        // Ambil pelanggan menunggak (hanya yang punya hutang)
        $overdueCustomers = $this->collectorService->getOverdueCustomersForDashboard(
            $collector,
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
            $request->get('payment_status'),
            20
        );

        // Get stats for display
        $customerIds = Customer::where('collector_id', $collector->id)->pluck('id')->toArray();
        $currentMonth = now()->month;
        $currentYear = now()->year;

        // Hitung pelanggan yang sudah lunas bulan ini (punya invoice bulan ini dengan status paid)
        $paidCount = Customer::whereIn('id', $customerIds)
            ->whereHas('invoices', function ($q) use ($currentMonth, $currentYear) {
                $q->where('period_month', $currentMonth)
                    ->where('period_year', $currentYear)
                    ->where('status', 'paid');
            })
            ->count();

        // Hitung pelanggan yang belum bayar bulan ini (punya invoice bulan ini dengan status pending/partial/overdue)
        $unpaidCount = Customer::whereIn('id', $customerIds)
            ->whereHas('invoices', function ($q) use ($currentMonth, $currentYear) {
                $q->where('period_month', $currentMonth)
                    ->where('period_year', $currentYear)
                    ->whereIn('status', ['pending', 'partial', 'overdue']);
            })
            ->count();

        $stats = [
            'total' => count($customerIds),
            'paid' => $paidCount,
            'unpaid' => $unpaidCount,
            'isolated' => Customer::whereIn('id', $customerIds)->where('status', 'isolated')->count(),
        ];

        return Inertia::render('Collector/Customers', [
            'customers' => $customers,
            'stats' => $stats,
            'filters' => [
                'search' => $request->get('search'),
                'status' => $request->get('status'),
                'payment_status' => $request->get('payment_status'),
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

            $message = 'Pembayaran Rp ' . number_format($request->amount, 0, ',', '.') . ' berhasil dicatat';
            if ($result['access_opened']) {
                $message .= '. Akses internet pelanggan telah dibuka kembali.';
            }

            return back()->with('success', $message);

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

            $message = 'Pembayaran transfer Rp ' . number_format($request->amount, 0, ',', '.') . ' berhasil dicatat';
            if ($result['access_opened']) {
                $message .= '. Akses internet pelanggan telah dibuka kembali.';
            }

            return back()->with('success', $message);

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

        // Return Inertia redirect with whatsapp_url in flash data
        return back()->with('whatsapp_url', $whatsappUrl);
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

    // ================================================================
    // MAPPING / PETA
    // ================================================================

    /**
     * Tampilkan halaman mapping untuk penagih
     */
    public function mapping(Request $request)
    {
        $collector = auth()->user();
        $areas = Area::active()->orderBy('name')->get(['id', 'name']);

        // Get center point from first customer with coordinates
        $centerPoint = $this->getMappingCenterPoint($collector);

        return Inertia::render('Collector/Mapping', [
            'areas' => $areas,
            'centerPoint' => $centerPoint,
            'filters' => $request->only(['area_id', 'show_customers', 'show_odps', 'status']),
        ]);
    }

    /**
     * Get customers dengan koordinat untuk map (hanya pelanggan penagih ini)
     */
    public function getMappingCustomers(Request $request)
    {
        $collector = auth()->user();

        $query = Customer::select([
            'id',
            'customer_id',
            'name',
            'address',
            'phone',
            'status',
            'latitude',
            'longitude',
            'odp_id',
            'area_id',
            'package_id',
            'total_debt',
        ])
            ->where('collector_id', $collector->id)
            ->with(['package:id,name', 'area:id,name', 'odp:id,name,code'])
            ->whereNotNull('latitude')
            ->whereNotNull('longitude');

        if ($request->filled('area_id')) {
            $query->where('area_id', $request->area_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('bounds')) {
            $bounds = $request->bounds;
            $query->whereBetween('latitude', [$bounds['south'], $bounds['north']])
                ->whereBetween('longitude', [$bounds['west'], $bounds['east']]);
        }

        $customers = $query->limit(500)->get()->map(function ($customer) {
            return [
                'id' => $customer->id,
                'customer_id' => $customer->customer_id,
                'name' => $customer->name,
                'address' => $customer->address,
                'phone' => $customer->phone,
                'status' => $customer->status,
                'lat' => (float) $customer->latitude,
                'lng' => (float) $customer->longitude,
                'package' => $customer->package?->name,
                'area' => $customer->area?->name,
                'total_debt' => $customer->total_debt,
                'odp' => $customer->odp ? [
                    'id' => $customer->odp->id,
                    'name' => $customer->odp->name,
                    'code' => $customer->odp->code,
                ] : null,
            ];
        });

        return response()->json(['customers' => $customers]);
    }

    /**
     * Get ODPs dengan koordinat untuk map
     */
    public function getMappingOdps(Request $request)
    {
        $query = Odp::select([
            'id',
            'name',
            'code',
            'latitude',
            'longitude',
            'pole_type',
            'capacity',
            'used_ports',
            'area_id',
            'is_active',
        ])
            ->with(['area:id,name'])
            ->whereNotNull('latitude')
            ->whereNotNull('longitude');

        if ($request->filled('area_id')) {
            $query->where('area_id', $request->area_id);
        }

        if ($request->has('active_only')) {
            $query->active();
        }

        if ($request->filled('bounds')) {
            $bounds = $request->bounds;
            $query->whereBetween('latitude', [$bounds['south'], $bounds['north']])
                ->whereBetween('longitude', [$bounds['west'], $bounds['east']]);
        }

        $odps = $query->limit(200)->get()->map(function ($odp) {
            return [
                'id' => $odp->id,
                'name' => $odp->name,
                'code' => $odp->code,
                'lat' => (float) $odp->latitude,
                'lng' => (float) $odp->longitude,
                'pole_type' => $odp->pole_type,
                'pole_type_label' => $odp->pole_type_label,
                'capacity' => $odp->capacity,
                'used_ports' => $odp->used_ports,
                'available_ports' => $odp->available_ports,
                'usage_percentage' => $odp->usage_percentage,
                'area' => $odp->area?->name,
                'is_active' => $odp->is_active,
            ];
        });

        return response()->json(['odps' => $odps]);
    }

    /**
     * Get center point untuk initial map view
     */
    protected function getMappingCenterPoint($collector): array
    {
        // Try to get first customer with coordinates
        $customer = Customer::where('collector_id', $collector->id)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->first(['latitude', 'longitude']);

        if ($customer) {
            return [
                'lat' => (float) $customer->latitude,
                'lng' => (float) $customer->longitude,
            ];
        }

        // Try to get first ODP with coordinates
        $odp = Odp::whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->first(['latitude', 'longitude']);

        if ($odp) {
            return [
                'lat' => (float) $odp->latitude,
                'lng' => (float) $odp->longitude,
            ];
        }

        // Default to Kantor Kecamatan Pule, Trenggalek
        return [
            'lat' => -8.1228,
            'lng' => 111.5617,
        ];
    }
}
