<?php

namespace App\Http\Controllers\Collector;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Customer;
use App\Models\Odp;
use App\Models\Package;
use App\Http\Requests\Collector\Customer\StoreCollectorCustomerRequest;
use App\Http\Requests\Collector\Customer\UpdateCollectorCustomerRequest;
use App\Models\Router;
use App\Services\Billing\InvoiceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Inertia\Inertia;

class CustomerController extends Controller
{
    /**
     * Show create customer form
     */
    public function create()
    {
        return Inertia::render('Collector/CustomerForm', [
            'customer' => null,
            'packages' => Package::where('is_active', true)->orderBy('name')->get(),
            'areas' => Area::where('is_active', true)->orderBy('name')->get(),
            'routers' => Router::where('is_active', true)->orderBy('name')->get(),
            'odps' => Odp::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code', 'area_id']),
        ]);
    }

    /**
     * Store new customer
     */
    public function store(StoreCollectorCustomerRequest $request)
    {
        $validated = $request->validated();

        // Set default discount_type if not provided
        $validated['discount_type'] = $validated['discount_type'] ?? 'none';

        // Generate customer ID
        $lastCustomer = Customer::orderBy('id', 'desc')->first();
        $nextNumber = $lastCustomer ? $lastCustomer->id + 1 : 1;
        $validated['customer_id'] = 'CUST' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);

        // Set defaults - otomatis
        $validated['status'] = 'active';
        $validated['kecamatan'] = $validated['kecamatan'] ?? 'Pule';
        $validated['billing_type'] = 'prepaid'; // Otomatis bayar di muka
        $validated['pppoe_password'] = Crypt::encryptString('client001'); // Otomatis client001
        $initialDebt = $validated['total_debt'] ?? 0;
        $validated['total_debt'] = 0; // akan diisi oleh createHistoricalInvoice
        $validated['billing_start_date'] = $validated['billing_start_date'] ?: null;
        $validated['join_date'] = now();
        $validated['collector_id'] = Auth::id(); // Assign to current collector

        // Set rapel jika ada rapel_months
        if (!empty($validated['rapel_months']) && $validated['rapel_months'] > 0) {
            $validated['is_rapel'] = true;
            $validated['payment_behavior'] = 'rapel';
        }

        $customer = Customer::create($validated);

        // Buat invoice untuk hutang awal agar bisa dibayar/dilunasi
        if ($initialDebt > 0) {
            $invoiceService = app(InvoiceService::class);
            $previousMonth = now()->subMonth();
            $invoiceService->createHistoricalInvoice(
                $customer,
                $previousMonth->month,
                $previousMonth->year,
                $initialDebt,
                'Hutang awal pelanggan'
            );
        }

        // ODP used_ports recalculation handled by CustomerObserver

        return redirect()->route('collector.customer.detail', $customer)
            ->with('success', 'Pelanggan baru berhasil ditambahkan');
    }

    /**
     * Show edit customer form
     */
    public function edit(Customer $customer)
    {
        // Check if this customer belongs to this collector
        if ($customer->collector_id !== Auth::id()) {
            return redirect()->route('collector.customers')
                ->with('error', 'Anda tidak memiliki akses ke pelanggan ini');
        }

        // Decrypt PPPoE password for display
        $customerData = $customer->toArray();
        if ($customer->pppoe_password) {
            try {
                $customerData['pppoe_password'] = Crypt::decryptString($customer->pppoe_password);
            } catch (\Exception $e) {
                $customerData['pppoe_password'] = $customer->pppoe_password;
            }
        }

        return Inertia::render('Collector/CustomerForm', [
            'customer' => $customerData,
            'packages' => Package::where('is_active', true)->orderBy('name')->get(),
            'areas' => Area::where('is_active', true)->orderBy('name')->get(),
            'routers' => Router::where('is_active', true)->orderBy('name')->get(),
            'odps' => Odp::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code', 'area_id']),
        ]);
    }

    /**
     * Update customer
     */
    public function update(UpdateCollectorCustomerRequest $request, Customer $customer)
    {
        // Check if this customer belongs to this collector
        if ($customer->collector_id !== Auth::id()) {
            return redirect()->route('collector.customers')
                ->with('error', 'Anda tidak memiliki akses ke pelanggan ini');
        }

        $validated = $request->validated();

        // Set default discount_type if not provided
        $validated['discount_type'] = $validated['discount_type'] ?? 'none';

        // Set billing_start_date null if empty
        $validated['billing_start_date'] = $validated['billing_start_date'] ?: null;

        // Set rapel jika ada rapel_months
        if (!empty($validated['rapel_months']) && $validated['rapel_months'] > 0) {
            $validated['is_rapel'] = true;
            $validated['payment_behavior'] = 'rapel';
        } else {
            $validated['is_rapel'] = false;
            $validated['rapel_months'] = null;
        }

        $customer->update($validated);

        // ODP used_ports recalculation handled by CustomerObserver

        return redirect()->route('collector.customer.detail', $customer)
            ->with('success', 'Data pelanggan berhasil diperbarui');
    }
}
