<?php

namespace App\Http\Controllers\Collector;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Customer;
use App\Models\Odp;
use App\Models\Package;
use App\Models\Router;
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
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'address' => 'required|string|max:500',
            'kelurahan' => 'nullable|string|max:100',
            'package_id' => 'required|exists:packages,id',
            'area_id' => 'required|exists:areas,id',
            'router_id' => 'nullable|exists:routers,id',
            'odp_id' => 'nullable|exists:odps,id',
            'pppoe_username' => 'nullable|string|max:100',
            'ip_address' => 'nullable|string|max:45',
            'onu_serial' => 'nullable|string|max:100',
            'connection_type' => 'required|in:pppoe,static',
            'billing_date' => 'required|integer|min:1|max:28',
            'billing_start_date' => 'nullable|date',
            'total_debt' => 'nullable|numeric|min:0',
            'rapel_months' => 'nullable|integer|min:0|max:12',
            'discount_type' => 'nullable|in:none,nominal,percentage',
            'discount_value' => 'nullable|numeric|min:0',
            'discount_reason' => 'nullable|string|max:255',
            'is_taxed' => 'boolean',
            'notes' => 'nullable|string|max:1000',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        // Set default discount_type if not provided
        $validated['discount_type'] = $validated['discount_type'] ?? 'none';

        // Generate customer ID
        $lastCustomer = Customer::orderBy('id', 'desc')->first();
        $nextNumber = $lastCustomer ? $lastCustomer->id + 1 : 1;
        $validated['customer_id'] = 'CUST' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);

        // Set defaults - otomatis
        $validated['status'] = 'active';
        $validated['kecamatan'] = 'Pule'; // Otomatis Pule
        $validated['billing_type'] = 'prepaid'; // Otomatis bayar di muka
        $validated['pppoe_password'] = Crypt::encryptString('client001'); // Otomatis client001
        $validated['total_debt'] = $validated['total_debt'] ?? 0;
        $validated['billing_start_date'] = $validated['billing_start_date'] ?: null;
        $validated['join_date'] = now();
        $validated['collector_id'] = Auth::id(); // Assign to current collector

        // Set rapel jika ada rapel_months
        if (!empty($validated['rapel_months']) && $validated['rapel_months'] > 0) {
            $validated['is_rapel'] = true;
            $validated['payment_behavior'] = 'rapel';
        }

        $customer = Customer::create($validated);

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
    public function update(Request $request, Customer $customer)
    {
        // Check if this customer belongs to this collector
        if ($customer->collector_id !== Auth::id()) {
            return redirect()->route('collector.customers')
                ->with('error', 'Anda tidak memiliki akses ke pelanggan ini');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'address' => 'required|string|max:500',
            'kelurahan' => 'nullable|string|max:100',
            'package_id' => 'required|exists:packages,id',
            'area_id' => 'required|exists:areas,id',
            'router_id' => 'nullable|exists:routers,id',
            'odp_id' => 'nullable|exists:odps,id',
            'pppoe_username' => 'nullable|string|max:100',
            'ip_address' => 'nullable|string|max:45',
            'onu_serial' => 'nullable|string|max:100',
            'connection_type' => 'required|in:pppoe,static',
            'billing_date' => 'required|integer|min:1|max:28',
            'billing_start_date' => 'nullable|date',
            'total_debt' => 'nullable|numeric|min:0',
            'rapel_months' => 'nullable|integer|min:0|max:12',
            'discount_type' => 'nullable|in:none,nominal,percentage',
            'discount_value' => 'nullable|numeric|min:0',
            'discount_reason' => 'nullable|string|max:255',
            'is_taxed' => 'boolean',
            'notes' => 'nullable|string|max:1000',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

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

        return redirect()->route('collector.customer.detail', $customer)
            ->with('success', 'Data pelanggan berhasil diperbarui');
    }
}
