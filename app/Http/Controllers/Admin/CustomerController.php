<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Area;
use App\Models\Router;
use App\Models\User;
use App\Services\Billing\DebtService;
use App\Imports\CustomerImport;
use App\Exports\CustomerTemplateExport;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class CustomerController extends Controller
{
    protected DebtService $debtService;

    public function __construct(DebtService $debtService)
    {
        $this->debtService = $debtService;
    }

    /**
     * Display customer list
     */
    public function index(Request $request)
    {
        $query = Customer::with(['package', 'area', 'router', 'collector'])
            ->withCount('invoices');

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by area
        if ($request->filled('area_id')) {
            $query->where('area_id', $request->area_id);
        }

        // Filter by package
        if ($request->filled('package_id')) {
            $query->where('package_id', $request->package_id);
        }

        // Filter by collector
        if ($request->filled('collector_id')) {
            $query->where('collector_id', $request->collector_id);
        }

        // Filter by debt
        if ($request->get('has_debt')) {
            $query->where('total_debt', '>', 0);
        }

        // Filter will isolate (2+ months debt)
        if ($request->get('will_isolate')) {
            $query->where('status', 'active')
                ->whereHas('invoices', function ($q) {
                    $q->whereIn('status', ['pending', 'partial', 'overdue']);
                }, '>=', 2);
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('customer_id', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%")
                    ->orWhere('pppoe_username', 'like', "%{$search}%");
            });
        }

        // Sort - validate sort field to prevent SQL errors
        $allowedSortFields = ['created_at', 'name', 'customer_id', 'total_debt', 'status', 'join_date'];
        $sortField = $request->get('sort', 'created_at');
        // Map 'debt' to 'total_debt' for compatibility
        if ($sortField === 'debt') {
            $sortField = 'total_debt';
        }
        if (!in_array($sortField, $allowedSortFields)) {
            $sortField = 'created_at';
        }
        $sortDirection = $request->get('direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        $customers = $query->paginate($request->get('per_page', 15))
            ->withQueryString();

        return Inertia::render('Admin/Customer/Index', [
            'customers' => $customers,
            'filters' => $request->only(['status', 'area_id', 'package_id', 'collector_id', 'search', 'has_debt']),
            'areas' => Area::active()->get(['id', 'name']),
            'packages' => Package::active()->get(['id', 'name']),
            'collectors' => User::where('role', 'penagih')->where('is_active', true)->get(['id', 'name']),
        ]);
    }

    /**
     * Show create form
     */
    public function create()
    {
        return Inertia::render('Admin/Customer/Form', [
            'customer' => null,
            'packages' => Package::active()->ordered()->get(),
            'areas' => Area::active()->with('router')->get(),
            'routers' => Router::active()->get(),
            'collectors' => User::where('role', 'penagih')->where('is_active', true)->get(['id', 'name']),
        ]);
    }

    /**
     * Store new customer
     */
    public function store(Request $request)
    {
        // Convert empty strings to null for optional fields
        $request->merge([
            'ip_address' => $request->ip_address ?: null,
            'email' => $request->email ?: null,
            'latitude' => $request->latitude ?: null,
            'longitude' => $request->longitude ?: null,
            'collector_id' => $request->collector_id ?: null,
        ]);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:500',
            'rt_rw' => 'nullable|string|max:20',
            'kelurahan' => 'nullable|string|max:100',
            'kecamatan' => 'nullable|string|max:100',
            'phone' => 'required|string|max:20',
            'phone_alt' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'nik' => 'nullable|string|max:20',
            'package_id' => 'required|exists:packages,id',
            'area_id' => 'required|exists:areas,id',
            'router_id' => 'required|exists:routers,id',
            'collector_id' => 'nullable|exists:users,id',
            'pppoe_username' => 'nullable|string|max:100|unique:customers,pppoe_username',
            'pppoe_password' => 'nullable|string|max:100',
            'ip_address' => 'nullable|ip',
            'mac_address' => 'nullable|string|max:20',
            'onu_serial' => 'nullable|string|max:50',
            'billing_type' => 'required|in:prepaid,postpaid',
            'billing_date' => 'nullable|integer|min:1|max:28',
            'is_rapel' => 'boolean',
            'rapel_months' => 'nullable|integer|min:1|max:12',
            'notes' => 'nullable|string|max:1000',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        // Generate customer ID
        $lastCustomer = Customer::orderBy('id', 'desc')->first();
        $sequence = $lastCustomer ? intval(substr($lastCustomer->customer_id, -5)) + 1 : 1;
        $validated['customer_id'] = 'JIN-' . str_pad($sequence, 5, '0', STR_PAD_LEFT);

        $validated['status'] = Customer::STATUS_ACTIVE;
        $validated['join_date'] = now();
        $validated['total_debt'] = 0;

        $customer = Customer::create($validated);

        return redirect()->route('admin.customers.show', $customer)
            ->with('success', 'Pelanggan berhasil ditambahkan');
    }

    /**
     * Show customer detail
     */
    public function show(Customer $customer)
    {
        $customer->load([
            'package',
            'area',
            'router',
            'collector',
            'invoices' => fn($q) => $q->orderBy('period_year', 'desc')->orderBy('period_month', 'desc')->limit(12),
            'payments' => fn($q) => $q->orderBy('created_at', 'desc')->limit(10),
            'debtHistories' => fn($q) => $q->orderBy('created_at', 'desc')->limit(20),
        ]);

        $debtSummary = $this->debtService->getDebtSummary($customer);

        return Inertia::render('Admin/Customer/Show', [
            'customer' => $customer,
            'debtSummary' => $debtSummary,
        ]);
    }

    /**
     * Show edit form
     */
    public function edit(Customer $customer)
    {
        return Inertia::render('Admin/Customer/Form', [
            'customer' => $customer,
            'packages' => Package::active()->ordered()->get(),
            'areas' => Area::active()->with('router')->get(),
            'routers' => Router::active()->get(),
            'collectors' => User::where('role', 'penagih')->where('is_active', true)->get(['id', 'name']),
        ]);
    }

    /**
     * Update customer
     */
    public function update(Request $request, Customer $customer)
    {
        // Convert empty strings to null for optional fields
        $request->merge([
            'ip_address' => $request->ip_address ?: null,
            'email' => $request->email ?: null,
            'latitude' => $request->latitude ?: null,
            'longitude' => $request->longitude ?: null,
            'collector_id' => $request->collector_id ?: null,
        ]);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:500',
            'rt_rw' => 'nullable|string|max:20',
            'kelurahan' => 'nullable|string|max:100',
            'kecamatan' => 'nullable|string|max:100',
            'phone' => 'required|string|max:20',
            'phone_alt' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'nik' => 'nullable|string|max:20',
            'package_id' => 'required|exists:packages,id',
            'area_id' => 'required|exists:areas,id',
            'router_id' => 'required|exists:routers,id',
            'collector_id' => 'nullable|exists:users,id',
            'pppoe_username' => ['nullable', 'string', 'max:100', Rule::unique('customers')->ignore($customer->id)],
            'pppoe_password' => 'nullable|string|max:100',
            'ip_address' => 'nullable|ip',
            'mac_address' => 'nullable|string|max:20',
            'onu_serial' => 'nullable|string|max:50',
            'status' => 'required|in:active,isolated,suspended,terminated',
            'billing_type' => 'required|in:prepaid,postpaid',
            'billing_date' => 'nullable|integer|min:1|max:28',
            'is_rapel' => 'boolean',
            'rapel_months' => 'nullable|integer|min:1|max:12',
            'notes' => 'nullable|string|max:1000',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        $customer->update($validated);

        return redirect()->route('admin.customers.show', $customer)
            ->with('success', 'Data pelanggan berhasil diperbarui');
    }

    /**
     * Delete customer (soft delete)
     */
    public function destroy(Customer $customer)
    {
        // Check if customer has unpaid invoices
        $hasUnpaidInvoices = $customer->invoices()
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->exists();

        if ($hasUnpaidInvoices) {
            return back()->with('error', 'Tidak dapat menghapus pelanggan dengan tagihan belum lunas');
        }

        $customer->update([
            'status' => Customer::STATUS_TERMINATED,
            'termination_date' => now(),
            'termination_reason' => 'Dihapus oleh admin',
        ]);

        $customer->delete();

        return redirect()->route('admin.customers.index')
            ->with('success', 'Pelanggan berhasil dihapus');
    }

    /**
     * Adjust customer debt
     */
    public function adjustDebt(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric',
            'reason' => 'required|string|max:500',
        ]);

        $this->debtService->adjustDebt($customer, $validated['amount'], $validated['reason']);

        return back()->with('success', 'Hutang pelanggan berhasil disesuaikan');
    }

    /**
     * Write off customer debt
     */
    public function writeOffDebt(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0|max:' . $customer->total_debt,
            'reason' => 'required|string|max:500',
        ]);

        $this->debtService->writeOffDebt($customer, $validated['amount'], $validated['reason']);

        return back()->with('success', 'Hutang pelanggan berhasil di-write off');
    }

    /**
     * Recalculate customer debt
     */
    public function recalculateDebt(Customer $customer)
    {
        $result = $this->debtService->recalculateDebt($customer);

        if ($result['adjusted']) {
            return back()->with('success', "Hutang berhasil direkalkukasi. Perubahan: Rp " . number_format(abs($result['difference']), 0, ',', '.'));
        }

        return back()->with('info', 'Hutang sudah sesuai, tidak ada perubahan');
    }

    /**
     * Download import template
     */
    public function downloadTemplate()
    {
        return Excel::download(new CustomerTemplateExport, 'template_import_pelanggan.xlsx');
    }

    /**
     * Import customers from Excel
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv|max:10240', // max 10MB
        ], [
            'file.required' => 'File Excel wajib diupload',
            'file.mimes' => 'Format file harus xlsx, xls, atau csv',
            'file.max' => 'Ukuran file maksimal 10MB',
        ]);

        try {
            // Count before import
            $countBefore = Customer::count();

            $import = new CustomerImport();
            Excel::import($import, $request->file('file'));

            // Count after import
            $countAfter = Customer::count();
            $importedCount = $countAfter - $countBefore;

            $failures = $import->failures();
            $errors = $import->errors();

            $failureCount = count($failures);
            $errorCount = count($errors);

            if ($failureCount > 0 || $errorCount > 0) {
                $errorMessages = [];

                foreach ($failures as $failure) {
                    $errorMessages[] = "Baris {$failure->row()}: " . implode(', ', $failure->errors());
                }

                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }

                // Limit error messages to first 5
                $errorMessages = array_slice($errorMessages, 0, 5);
                $moreErrors = ($failureCount + $errorCount) > 5 ? ' (+' . (($failureCount + $errorCount) - 5) . ' error lainnya)' : '';

                return back()->with('warning', "Import selesai: {$importedCount} pelanggan berhasil diimport. Error: " . implode('; ', $errorMessages) . $moreErrors);
            }

            if ($importedCount === 0) {
                return back()->with('info', 'Tidak ada pelanggan baru yang diimport. Kemungkinan data sudah ada atau format tidak sesuai.');
            }

            return back()->with('success', "Import berhasil! {$importedCount} pelanggan baru telah ditambahkan.");

        } catch (\Exception $e) {
            \Log::error('Customer import error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return back()->with('error', 'Gagal import: ' . $e->getMessage());
        }
    }
}
