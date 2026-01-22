<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Odp;
use App\Models\Area;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'required|string|max:50|unique:odps,code',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'pole_type' => ['required', Rule::in(array_keys(Odp::getPoleTypes()))],
            'capacity' => 'required|integer|min:1|max:255',
            'area_id' => 'nullable|exists:areas,id',
            'is_active' => 'boolean',
            'notes' => 'nullable|string|max:1000',
        ]);

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
    public function update(Request $request, Odp $odp)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'code' => ['required', 'string', 'max:50', Rule::unique('odps')->ignore($odp->id)],
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'pole_type' => ['required', Rule::in(array_keys(Odp::getPoleTypes()))],
            'capacity' => 'required|integer|min:1|max:255',
            'area_id' => 'nullable|exists:areas,id',
            'is_active' => 'boolean',
            'notes' => 'nullable|string|max:1000',
        ]);

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
