<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ReopenCustomerJob;
use App\Models\Area;
use App\Models\Customer;
use App\Models\Package;
use Illuminate\Http\Request;
use Inertia\Inertia;

class IsolationController extends Controller
{
    public function index(Request $request)
    {
        $query = Customer::with(['package', 'area', 'collector'])
            ->isolated();

        // Filter by area
        if ($request->filled('area_id')) {
            $query->where('area_id', $request->area_id);
        }

        // Filter by package
        if ($request->filled('package_id')) {
            $query->where('package_id', $request->package_id);
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

        // Sort
        $allowedSortFields = ['isolation_date', 'name', 'customer_id', 'total_debt'];
        $sortField = $request->get('sort', 'isolation_date');
        if (!in_array($sortField, $allowedSortFields)) {
            $sortField = 'isolation_date';
        }
        $sortDirection = $request->get('direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        $perPage = $request->get('per_page', 15);
        if ($perPage === 'all') {
            $customers = $query->paginate(999999)->withQueryString();
        } else {
            $customers = $query->paginate(min((int) $perPage, 999999))->withQueryString();
        }

        return Inertia::render('Admin/Isolation/Index', [
            'customers' => $customers,
            'filters' => $request->only(['area_id', 'package_id', 'search', 'per_page']),
            'areas' => Area::active()->get(['id', 'name']),
            'packages' => Package::active()->get(['id', 'name']),
        ]);
    }

    public function reopen(Customer $customer)
    {
        if (!$customer->isIsolated()) {
            return back()->with('error', 'Pelanggan tidak dalam status isolir.');
        }

        ReopenCustomerJob::dispatch($customer->id);

        return back()->with('success', "Proses buka isolir untuk {$customer->name} sedang dijalankan.");
    }
}
