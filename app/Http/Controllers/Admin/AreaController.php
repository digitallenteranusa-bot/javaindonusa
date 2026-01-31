<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AreaController extends Controller
{
    /**
     * Display area list
     */
    public function index(Request $request)
    {
        $query = Area::with(['collector', 'parent'])
            ->withCount('customers');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($request->has('active_only')) {
            $query->active();
        }

        $areas = $query->orderBy('name')->paginate($request->get('per_page', 15))
            ->withQueryString();

        return Inertia::render('Admin/Area/Index', [
            'areas' => $areas,
            'filters' => $request->only(['search', 'active_only']),
        ]);
    }

    /**
     * Show create form
     */
    public function create()
    {
        return Inertia::render('Admin/Area/Form', [
            'area' => null,
            'collectors' => User::where('role', 'penagih')->where('is_active', true)->get(['id', 'name']),
            'parentAreas' => Area::active()->root()->get(['id', 'name']),
        ]);
    }

    /**
     * Store new area
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'required|string|max:20|unique:areas,code',
            'description' => 'nullable|string|max:500',
            'parent_id' => 'nullable|exists:areas,id',
            'collector_id' => 'nullable|exists:users,id',
            'is_active' => 'boolean',
            'coverage_radius' => 'nullable|numeric|min:0',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        $validated['is_active'] = $validated['is_active'] ?? true;

        $area = Area::create($validated);

        return redirect()->route('admin.areas.index')
            ->with('success', 'Area berhasil ditambahkan');
    }

    /**
     * Show area detail
     */
    public function show(Area $area)
    {
        $area->load(['collector', 'parent', 'children']);
        $area->loadCount('customers');

        // Get customers in this area
        $customers = $area->customers()
            ->with('package')
            ->orderBy('name')
            ->limit(20)
            ->get();

        return Inertia::render('Admin/Area/Show', [
            'area' => $area,
            'customers' => $customers,
        ]);
    }

    /**
     * Show edit form
     */
    public function edit(Area $area)
    {
        return Inertia::render('Admin/Area/Form', [
            'area' => $area,
            'collectors' => User::where('role', 'penagih')->where('is_active', true)->get(['id', 'name']),
            'parentAreas' => Area::active()->root()->where('id', '!=', $area->id)->get(['id', 'name']),
        ]);
    }

    /**
     * Update area
     */
    public function update(Request $request, Area $area)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'code' => ['required', 'string', 'max:20', Rule::unique('areas')->ignore($area->id)],
            'description' => 'nullable|string|max:500',
            'parent_id' => 'nullable|exists:areas,id',
            'collector_id' => 'nullable|exists:users,id',
            'is_active' => 'boolean',
            'coverage_radius' => 'nullable|numeric|min:0',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        // Prevent self-reference
        if (isset($validated['parent_id']) && $validated['parent_id'] == $area->id) {
            return back()->with('error', 'Area tidak dapat menjadi parent dari dirinya sendiri');
        }

        $area->update($validated);

        return redirect()->route('admin.areas.index')
            ->with('success', 'Area berhasil diperbarui');
    }

    /**
     * Delete area
     */
    public function destroy(Area $area)
    {
        // Check if area has customers
        if ($area->customers()->exists()) {
            return back()->with('error', 'Tidak dapat menghapus area yang masih memiliki pelanggan');
        }

        // Check if area has ODPs
        if ($area->odps()->exists()) {
            return back()->with('error', 'Tidak dapat menghapus area yang masih memiliki ODP. Hapus atau pindahkan ODP terlebih dahulu.');
        }

        // Check if any sub-areas have customers
        $childrenWithCustomers = $area->children()
            ->whereHas('customers')
            ->exists();

        if ($childrenWithCustomers) {
            return back()->with('error', 'Tidak dapat menghapus area karena sub-area masih memiliki pelanggan');
        }

        // Check if any sub-areas have ODPs
        $childrenWithOdps = $area->children()
            ->whereHas('odps')
            ->exists();

        if ($childrenWithOdps) {
            return back()->with('error', 'Tidak dapat menghapus area karena sub-area masih memiliki ODP');
        }

        // Cascade delete: delete all sub-areas first
        $area->children()->delete();

        // Delete the area
        $area->delete();

        return redirect()->route('admin.areas.index')
            ->with('success', 'Area dan sub-area berhasil dihapus');
    }

    /**
     * Toggle area active status
     */
    public function toggleActive(Area $area)
    {
        $area->update(['is_active' => !$area->is_active]);

        $status = $area->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return back()->with('success', "Area berhasil {$status}");
    }
}
