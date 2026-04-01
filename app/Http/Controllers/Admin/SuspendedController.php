<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\UnsuspendCustomerJob;
use App\Models\Area;
use App\Models\Customer;
use App\Models\Package;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SuspendedController extends Controller
{
    public function index(Request $request)
    {
        $query = Customer::with(['package', 'area', 'collector'])
            ->suspended();

        if ($request->filled('area_id')) {
            $query->where('area_id', $request->area_id);
        }

        if ($request->filled('package_id')) {
            $query->where('package_id', $request->package_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('customer_id', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%");
            });
        }

        $sortField = $request->get('sort', 'suspension_start_date');
        $allowedSortFields = ['suspension_start_date', 'name', 'customer_id'];
        if (!in_array($sortField, $allowedSortFields)) {
            $sortField = 'suspension_start_date';
        }
        $query->orderBy($sortField, $request->get('direction', 'desc'));

        $perPage = $request->get('per_page', 15);
        if ($perPage === 'all') {
            $customers = $query->paginate(999999)->withQueryString();
        } else {
            $customers = $query->paginate(min((int) $perPage, 999999))->withQueryString();
        }

        return Inertia::render('Admin/Suspended/Index', [
            'customers' => $customers,
            'filters' => $request->only(['area_id', 'package_id', 'search', 'per_page']),
            'areas' => Area::active()->get(['id', 'name']),
            'packages' => Package::active()->get(['id', 'name']),
        ]);
    }

    public function unsuspend(Customer $customer)
    {
        if ($customer->status !== Customer::STATUS_SUSPENDED) {
            return back()->with('error', 'Pelanggan tidak dalam status cuti.');
        }

        UnsuspendCustomerJob::dispatch($customer->id);

        return back()->with('success', "Proses aktivasi kembali untuk {$customer->name} sedang dijalankan.");
    }
}
