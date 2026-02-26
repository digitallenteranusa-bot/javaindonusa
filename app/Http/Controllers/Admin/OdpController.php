<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Odp\StoreOdpRequest;
use App\Http\Requests\Admin\Odp\UpdateOdpRequest;
use App\Models\Odp;
use App\Models\Area;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OdpController extends Controller
{
    /**
     * Display ODP list
     */
    public function index(Request $request)
    {
        $query = Odp::with(['area']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($request->filled('area_id')) {
            $query->where('area_id', $request->area_id);
        }

        if ($request->filled('pole_type')) {
            $query->where('pole_type', $request->pole_type);
        }

        if ($request->has('active_only')) {
            $query->active();
        }

        if ($request->has('has_available')) {
            $query->hasAvailablePorts();
        }

        $odps = $query->withCount('customers')
            ->orderBy('code')
            ->paginate($request->get('per_page', 15))
            ->withQueryString();

        return Inertia::render('Admin/Odp/Index', [
            'odps' => $odps,
            'filters' => $request->only(['search', 'area_id', 'pole_type', 'active_only', 'has_available']),
            'areas' => Area::active()->orderBy('name')->get(['id', 'name']),
            'poleTypes' => Odp::getPoleTypes(),
        ]);
    }

    /**
     * Show create form
     */
    public function create()
    {
        return Inertia::render('Admin/Odp/Form', [
            'odp' => null,
            'areas' => Area::active()->orderBy('name')->get(['id', 'name']),
            'poleTypes' => Odp::getPoleTypes(),
        ]);
    }

    /**
     * Store new ODP
     */
    public function store(StoreOdpRequest $request)
    {
        $validated = $request->validated();

        $validated['is_active'] = $validated['is_active'] ?? true;
        $validated['used_ports'] = 0;

        Odp::create($validated);

        return redirect()->route('admin.odps.index')
            ->with('success', 'ODP berhasil ditambahkan');
    }

    /**
     * Show ODP detail
     */
    public function show(Odp $odp)
    {
        $odp->load('area');
        $odp->loadCount('customers');

        $customers = $odp->customers()
            ->with('package')
            ->orderBy('name')
            ->limit(20)
            ->get();

        return Inertia::render('Admin/Odp/Show', [
            'odp' => $odp,
            'customers' => $customers,
        ]);
    }

    /**
     * Show edit form
     */
    public function edit(Odp $odp)
    {
        return Inertia::render('Admin/Odp/Form', [
            'odp' => $odp,
            'areas' => Area::active()->orderBy('name')->get(['id', 'name']),
            'poleTypes' => Odp::getPoleTypes(),
        ]);
    }

    /**
     * Update ODP
     */
    public function update(UpdateOdpRequest $request, Odp $odp)
    {
        $validated = $request->validated();

        $odp->update($validated);

        return redirect()->route('admin.odps.index')
            ->with('success', 'ODP berhasil diperbarui');
    }

    /**
     * Delete ODP
     */
    public function destroy(Odp $odp)
    {
        if ($odp->customers()->exists()) {
            return back()->with('error', 'Tidak dapat menghapus ODP yang masih memiliki pelanggan');
        }

        $odp->delete();

        return redirect()->route('admin.odps.index')
            ->with('success', 'ODP berhasil dihapus');
    }

    /**
     * Toggle ODP active status
     */
    public function toggleActive(Odp $odp)
    {
        $odp->update(['is_active' => !$odp->is_active]);

        $status = $odp->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return back()->with('success', "ODP berhasil {$status}");
    }

    /**
     * Get ODPs for select dropdown (API)
     */
    public function getForSelect(Request $request)
    {
        $query = Odp::active()->hasAvailablePorts();

        if ($request->filled('area_id')) {
            $query->where('area_id', $request->area_id);
        }

        return response()->json([
            'odps' => $query->orderBy('code')->get(['id', 'name', 'code', 'capacity', 'used_ports']),
        ]);
    }
}
