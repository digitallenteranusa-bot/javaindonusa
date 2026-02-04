<?php

namespace App\Http\Controllers\Collector;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Customer;
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
            'package_id' => 'required|exists:packages,id',
            'area_id' => 'required|exists:areas,id',
            'router_id' => 'nullable|exists:routers,id',
            'pppoe_username' => 'nullable|string|max:100',
            'pppoe_password' => 'nullable|string|max:100',
            'ip_address' => 'nullable|string|max:45',
            'onu_serial' => 'nullable|string|max:100',
            'connection_type' => 'required|in:pppoe,static',
            'billing_date' => 'required|integer|min:1|max:28',
            'notes' => 'nullable|string|max:1000',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        // Generate customer ID
        $lastCustomer = Customer::orderBy('id', 'desc')->first();
        $nextNumber = $lastCustomer ? $lastCustomer->id + 1 : 1;
        $validated['customer_id'] = 'CUST' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);

        // Set defaults
        $validated['status'] = 'active';
        $validated['billing_type'] = 'postpaid';
        $validated['total_debt'] = 0;
        $validated['join_date'] = now();
        $validated['collector_id'] = Auth::id(); // Assign to current collector

        // Encrypt PPPoE password
        if (!empty($validated['pppoe_password'])) {
            $validated['pppoe_password'] = Crypt::encryptString($validated['pppoe_password']);
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
            'package_id' => 'required|exists:packages,id',
            'area_id' => 'required|exists:areas,id',
            'router_id' => 'nullable|exists:routers,id',
            'pppoe_username' => 'nullable|string|max:100',
            'pppoe_password' => 'nullable|string|max:100',
            'ip_address' => 'nullable|string|max:45',
            'onu_serial' => 'nullable|string|max:100',
            'connection_type' => 'required|in:pppoe,static',
            'billing_date' => 'required|integer|min:1|max:28',
            'notes' => 'nullable|string|max:1000',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        // Encrypt PPPoE password if changed
        if (!empty($validated['pppoe_password'])) {
            $validated['pppoe_password'] = Crypt::encryptString($validated['pppoe_password']);
        } else {
            unset($validated['pppoe_password']);
        }

        $customer->update($validated);

        return redirect()->route('collector.customer.detail', $customer)
            ->with('success', 'Data pelanggan berhasil diperbarui');
    }
}
