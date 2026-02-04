<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class RouterBrandController extends Controller
{
    /**
     * Display router brand statistics
     */
    public function index(Request $request)
    {
        $search = $request->get('search');

        // Get router brand statistics
        $brands = Customer::select(
                DB::raw('UPPER(TRIM(onu_serial)) as brand'),
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN status = "active" THEN 1 ELSE 0 END) as active_count'),
                DB::raw('SUM(CASE WHEN status = "isolated" THEN 1 ELSE 0 END) as isolated_count'),
                DB::raw('SUM(CASE WHEN status = "suspended" THEN 1 ELSE 0 END) as suspended_count'),
                DB::raw('SUM(CASE WHEN status = "terminated" THEN 1 ELSE 0 END) as terminated_count')
            )
            ->whereNotNull('onu_serial')
            ->where('onu_serial', '!=', '')
            ->when($search, function ($query, $search) {
                $query->where('onu_serial', 'like', "%{$search}%");
            })
            ->groupBy(DB::raw('UPPER(TRIM(onu_serial))'))
            ->orderByDesc('total')
            ->get();

        // Calculate totals
        $totalRouters = $brands->sum('total');
        $totalActive = $brands->sum('active_count');
        $totalIsolated = $brands->sum('isolated_count');

        // Count customers without router info
        $noRouterCount = Customer::where(function ($q) {
            $q->whereNull('onu_serial')->orWhere('onu_serial', '');
        })->count();

        return Inertia::render('Admin/RouterBrand/Index', [
            'brands' => $brands,
            'stats' => [
                'total_routers' => $totalRouters,
                'total_active' => $totalActive,
                'total_isolated' => $totalIsolated,
                'no_router_count' => $noRouterCount,
                'unique_brands' => $brands->count(),
            ],
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    /**
     * Show customers with specific router brand
     */
    public function show(Request $request, string $brand)
    {
        $brand = urldecode($brand);

        $customers = Customer::with(['package', 'area'])
            ->whereRaw('UPPER(TRIM(onu_serial)) = ?', [strtoupper(trim($brand))])
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/RouterBrand/Show', [
            'brand' => $brand,
            'customers' => $customers,
        ]);
    }
}
