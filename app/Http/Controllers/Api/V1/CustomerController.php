<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CustomerController extends Controller
{
    /**
     * List customers (paginated, scoped by role).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Customer::with(['package', 'area']);

        // Scope by role: collectors only see their assigned customers
        $user = $request->user();
        if ($user->role === 'penagih') {
            $query->where('collector_id', $user->id);
        }

        // Filters
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('customer_id', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('area_id')) {
            $query->where('area_id', $request->input('area_id'));
        }

        if ($request->filled('package_id')) {
            $query->where('package_id', $request->input('package_id'));
        }

        $customers = $query->orderBy('name')->paginate($request->input('per_page', 15));

        return CustomerResource::collection($customers);
    }

    /**
     * Get customer detail.
     */
    public function show(Request $request, Customer $customer): CustomerResource
    {
        // Collectors can only view their assigned customers
        $user = $request->user();
        if ($user->role === 'penagih' && $customer->collector_id !== $user->id) {
            abort(403, 'Akses ditolak.');
        }

        $customer->load(['package', 'area']);

        return new CustomerResource($customer);
    }
}
