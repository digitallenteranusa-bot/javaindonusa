<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Customer;
use App\Services\Billing\DebtIsolationService;
use App\Services\PdfService;
use App\Exports\InvoiceExport;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;

class InvoiceController extends Controller
{
    protected DebtIsolationService $billingService;

    public function __construct(DebtIsolationService $billingService)
    {
        $this->billingService = $billingService;
    }

    /**
     * Display invoice list
     */
    public function index(Request $request)
    {
        $query = Invoice::with(['customer:id,customer_id,name,phone']);

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by period
        if ($request->filled('period_year')) {
            $query->where('period_year', $request->period_year);
        }
        if ($request->filled('period_month')) {
            $query->where('period_month', $request->period_month);
        }

        // Filter by customer
        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        // Filter overdue
        if ($request->get('overdue')) {
            $query->where('status', 'overdue')
                ->orWhere(function ($q) {
                    $q->whereIn('status', ['pending', 'partial'])
                        ->where('due_date', '<', now());
                });
        }

        // Search by invoice number or customer
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($cq) use ($search) {
                        $cq->where('name', 'like', "%{$search}%")
                            ->orWhere('customer_id', 'like', "%{$search}%");
                    });
            });
        }

        // Sort
        $sortField = $request->get('sort', 'created_at');
        $sortDirection = $request->get('direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        $invoices = $query->paginate($request->get('per_page', 15))
            ->withQueryString();

        // Get summary stats
        $stats = [
            'total' => Invoice::count(),
            'pending' => Invoice::where('status', 'pending')->count(),
            'partial' => Invoice::where('status', 'partial')->count(),
            'paid' => Invoice::where('status', 'paid')->count(),
            'overdue' => Invoice::where('status', 'overdue')->count(),
            'total_billed' => Invoice::whereIn('status', ['pending', 'partial', 'overdue'])->sum('total_amount'),
            'total_outstanding' => Invoice::whereIn('status', ['pending', 'partial', 'overdue'])->sum('remaining_amount'),
        ];

        return Inertia::render('Admin/Invoice/Index', [
            'invoices' => $invoices,
            'filters' => $request->only(['status', 'period_year', 'period_month', 'search', 'overdue']),
            'stats' => $stats,
            'years' => range(now()->year, now()->year - 2),
            'months' => collect(range(1, 12))->map(fn($m) => [
                'value' => $m,
                'label' => Carbon::create()->month($m)->translatedFormat('F'),
            ]),
        ]);
    }

    /**
     * Show invoice detail
     */
    public function show(Invoice $invoice)
    {
        $invoice->load([
            'customer:id,customer_id,name,address,phone,email,total_debt',
            'customer.package:id,name,price',
            'payments' => fn($q) => $q->orderBy('created_at', 'desc'),
        ]);

        return Inertia::render('Admin/Invoice/Show', [
            'invoice' => $invoice,
        ]);
    }

    /**
     * Generate invoices for current period
     */
    public function generate(Request $request)
    {
        $periodMonth = $request->get('month', now()->month);
        $periodYear = $request->get('year', now()->year);

        // Get customers without invoice for this period
        $periodFirstDay = Carbon::create($periodYear, $periodMonth, 1)->format('Y-m-d');
        $customers = Customer::whereIn('status', ['active', 'isolated'])
            ->whereDoesntHave('invoices', function ($q) use ($periodMonth, $periodYear) {
                $q->where('period_month', $periodMonth)
                    ->where('period_year', $periodYear);
            })
            // Filter pelanggan yang billing_start_date belum tercapai
            ->where(function ($q) use ($periodFirstDay) {
                $q->whereNull('billing_start_date')
                  ->orWhereRaw('DATE_FORMAT(billing_start_date, "%Y-%m-01") <= ?', [$periodFirstDay]);
            })
            ->with('package')
            ->get();

        $generated = 0;
        $errors = [];

        foreach ($customers as $customer) {
            try {
                $result = $this->billingService->addMonthlyDebtForCustomer(
                    $customer,
                    $periodMonth,
                    $periodYear
                );

                if ($result['added']) {
                    $generated++;
                }
            } catch (\Exception $e) {
                $errors[] = [
                    'customer_id' => $customer->customer_id,
                    'error' => $e->getMessage(),
                ];
            }
        }

        if ($generated > 0) {
            return back()->with('success', "Berhasil generate {$generated} invoice untuk periode {$periodMonth}/{$periodYear}");
        }

        return back()->with('info', 'Tidak ada invoice baru yang perlu di-generate');
    }

    /**
     * Get customers for invoice generation (for selection modal)
     */
    public function getCustomersWithoutInvoice(Request $request)
    {
        $periodMonth = (int) $request->get('month', now()->month);
        $periodYear = (int) $request->get('year', now()->year);
        $search = trim($request->get('search', ''));
        $showAll = $request->boolean('show_all', false);

        // Start with base query - active or isolated customers
        $query = Customer::whereIn('status', ['active', 'isolated'])
            ->with(['package:id,name,price', 'area:id,name']);

        // Search filter
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('customer_id', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%");
            });
        }

        // Filter pelanggan yang billing_start_date belum tercapai
        // billing_start_date = 2026-03-05 → startOfMonth = 2026-03-01 → tampil mulai periode Maret
        $periodFirstDay = Carbon::create($periodYear, $periodMonth, 1)->format('Y-m-d');
        $query->where(function ($q) use ($periodFirstDay) {
            $q->whereNull('billing_start_date')
              ->orWhereRaw('DATE_FORMAT(billing_start_date, "%Y-%m-01") <= ?', [$periodFirstDay]);
        });

        // Only filter customers without invoice if not showing all
        if (!$showAll) {
            $query->whereDoesntHave('invoices', function ($q) use ($periodMonth, $periodYear) {
                $q->where('period_month', $periodMonth)
                    ->where('period_year', $periodYear);
            });
        }

        $customers = $query->orderBy('name')->limit(100)->get([
            'id', 'customer_id', 'name', 'phone', 'status', 'package_id', 'area_id'
        ]);

        // Mark customers that already have invoice for this period
        $customersWithInvoice = Invoice::where('period_month', $periodMonth)
            ->where('period_year', $periodYear)
            ->pluck('customer_id')
            ->toArray();

        $customers = $customers->map(function ($customer) use ($customersWithInvoice) {
            $customer->has_invoice = in_array($customer->id, $customersWithInvoice);
            return $customer;
        });

        return response()->json([
            'customers' => $customers,
            'total' => $customers->count(),
            'period' => ['month' => $periodMonth, 'year' => $periodYear],
            'search' => $search,
            'show_all' => $showAll,
        ]);
    }

    /**
     * Generate invoices for selected customers
     */
    public function generateForSelected(Request $request)
    {
        $validated = $request->validate([
            'customer_ids' => 'required|array|min:1',
            'customer_ids.*' => 'exists:customers,id',
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2020|max:2099',
        ]);

        $periodMonth = $validated['month'];
        $periodYear = $validated['year'];

        $customers = Customer::whereIn('id', $validated['customer_ids'])
            ->whereIn('status', ['active', 'isolated'])
            ->with('package')
            ->get();

        $generated = 0;
        $skipped = 0;
        $errors = [];

        foreach ($customers as $customer) {
            try {
                // Check if invoice already exists
                $exists = Invoice::where('customer_id', $customer->id)
                    ->where('period_month', $periodMonth)
                    ->where('period_year', $periodYear)
                    ->exists();

                if ($exists) {
                    $skipped++;
                    continue;
                }

                $result = $this->billingService->addMonthlyDebtForCustomer(
                    $customer,
                    $periodMonth,
                    $periodYear
                );

                if ($result['added']) {
                    $generated++;
                }
            } catch (\Exception $e) {
                $errors[] = [
                    'customer_id' => $customer->customer_id,
                    'error' => $e->getMessage(),
                ];
            }
        }

        $message = "Berhasil generate {$generated} invoice";
        if ($skipped > 0) {
            $message .= ", {$skipped} dilewati (sudah ada)";
        }
        if (count($errors) > 0) {
            $message .= ", " . count($errors) . " gagal";
        }

        return back()->with('success', $message);
    }

    /**
     * Mark invoice as paid manually
     */
    public function markPaid(Request $request, Invoice $invoice)
    {
        $validated = $request->validate([
            'notes' => 'nullable|string|max:500',
        ]);

        $invoice->update([
            'status' => 'paid',
            'paid_amount' => $invoice->total_amount,
            'remaining_amount' => 0,
            'paid_at' => now(),
        ]);

        // Update customer debt
        $invoice->customer->recalculateTotalDebt();

        return back()->with('success', 'Invoice berhasil ditandai lunas');
    }

    /**
     * Cancel invoice
     */
    public function cancel(Request $request, Invoice $invoice)
    {
        // Only unpaid invoices can be cancelled
        if (in_array($invoice->status, ['paid', 'cancelled'])) {
            return back()->with('error', 'Invoice yang sudah lunas atau sudah dibatalkan tidak dapat dibatalkan');
        }

        // Check if invoice has payments
        if ($invoice->paid_amount > 0) {
            return back()->with('error', 'Invoice dengan pembayaran tidak dapat dibatalkan');
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        // Save reference before deletion
        $customer = $invoice->customer;
        $invoiceNumber = $invoice->invoice_number;

        // Force delete invoice (not soft delete) to avoid unique constraint blocking regeneration
        $invoice->forceDelete();

        // Update customer debt
        $customer->recalculateTotalDebt();

        return back()->with('success', "Invoice {$invoiceNumber} berhasil dibatalkan dan dihapus");
    }

    /**
     * Delete invoice permanently
     */
    public function destroy(Request $request, Invoice $invoice)
    {
        // Check if invoice has payments
        if ($invoice->paid_amount > 0) {
            return back()->with('error', 'Invoice dengan pembayaran tidak dapat dihapus. Gunakan fitur Batalkan terlebih dahulu.');
        }

        // Check if invoice is already paid
        if ($invoice->status === 'paid') {
            return back()->with('error', 'Invoice yang sudah lunas tidak dapat dihapus');
        }

        // Save customer reference before deletion
        $customer = $invoice->customer;
        $invoiceNumber = $invoice->invoice_number;

        // Delete the invoice permanently (force delete, not soft delete)
        $invoice->forceDelete();

        // Update customer debt if customer exists
        if ($customer) {
            $customer->recalculateTotalDebt();
        }

        return redirect()->route('admin.invoices.index')
            ->with('success', "Invoice {$invoiceNumber} berhasil dihapus");
    }

    /**
     * Update overdue status for all invoices
     */
    public function updateOverdueStatus()
    {
        $updated = Invoice::whereIn('status', ['pending', 'partial'])
            ->where('due_date', '<', now())
            ->update(['status' => 'overdue']);

        return back()->with('success', "{$updated} invoice diperbarui ke status overdue");
    }

    /**
     * Export invoices to Excel
     */
    public function export(Request $request)
    {
        $year = $request->filled('period_year') ? (int) $request->period_year : null;
        $month = $request->filled('period_month') ? (int) $request->period_month : null;
        $status = $request->filled('status') ? $request->status : null;

        $filename = 'invoices';
        if ($year && $month) {
            $filename .= "_{$year}-{$month}";
        } elseif ($year) {
            $filename .= "_{$year}";
        }
        if ($status) {
            $filename .= "_{$status}";
        }
        $filename .= '_' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(
            new InvoiceExport($year, $month, $status),
            $filename
        );
    }

    /**
     * Download invoice as PDF
     */
    public function downloadPdf(Invoice $invoice, PdfService $pdfService)
    {
        $pdf = $pdfService->generateInvoicePdf($invoice);

        return $pdf->download("invoice_{$invoice->invoice_number}.pdf");
    }

    /**
     * Stream invoice PDF (for preview)
     */
    public function streamPdf(Invoice $invoice, PdfService $pdfService)
    {
        $pdf = $pdfService->generateInvoicePdf($invoice);

        return $pdf->stream("invoice_{$invoice->invoice_number}.pdf");
    }

    /**
     * Bulk export invoices to PDF
     */
    public function bulkExportPdf(Request $request, PdfService $pdfService)
    {
        $validated = $request->validate([
            'invoice_ids' => 'required|array|min:1',
            'invoice_ids.*' => 'exists:invoices,id',
        ]);

        $pdf = $pdfService->generateBulkInvoicesPdf($validated['invoice_ids']);

        return $pdf->download('invoices_bulk_' . now()->format('Ymd_His') . '.pdf');
    }
}
