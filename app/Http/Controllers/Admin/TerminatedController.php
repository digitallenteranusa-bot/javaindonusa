<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Customer;
use App\Models\Package;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class TerminatedController extends Controller
{
    public function index(Request $request)
    {
        $query = Customer::with(['package', 'area', 'collector'])
            ->where('status', Customer::STATUS_TERMINATED);

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

        $sortField = $request->get('sort', 'updated_at');
        $allowedSortFields = ['updated_at', 'name', 'customer_id', 'total_debt'];
        if (!in_array($sortField, $allowedSortFields)) {
            $sortField = 'updated_at';
        }
        $query->orderBy($sortField, $request->get('direction', 'desc'));

        $perPage = $request->get('per_page', 15);
        if ($perPage === 'all') {
            $customers = $query->paginate(999999)->withQueryString();
        } else {
            $customers = $query->paginate(min((int) $perPage, 999999))->withQueryString();
        }

        return Inertia::render('Admin/Terminated/Index', [
            'customers' => $customers,
            'filters' => $request->only(['area_id', 'package_id', 'search', 'per_page']),
            'areas' => Area::active()->get(['id', 'name']),
            'packages' => Package::active()->get(['id', 'name']),
        ]);
    }

    public function reactivate(Customer $customer)
    {
        if ($customer->status !== Customer::STATUS_TERMINATED) {
            return back()->with('error', 'Pelanggan tidak dalam status terminated.');
        }

        $customer->update([
            'status' => Customer::STATUS_ACTIVE,
        ]);

        Log::info('Terminated customer reactivated', [
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'admin' => auth()->user()->name ?? 'unknown',
        ]);

        return back()->with('success', "{$customer->name} berhasil diaktifkan kembali.");
    }
}
