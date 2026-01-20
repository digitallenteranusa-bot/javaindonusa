<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Package;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class PackageController extends Controller
{
    /**
     * Display package list
     */
    public function index(Request $request)
    {
        $query = Package::withCount('customers');

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

        $packages = $query->ordered()->paginate($request->get('per_page', 15))
            ->withQueryString();

        return Inertia::render('Admin/Package/Index', [
            'packages' => $packages,
            'filters' => $request->only(['search', 'active_only']),
        ]);
    }

    /**
     * Show create form
     */
    public function create()
    {
        return Inertia::render('Admin/Package/Form', [
            'package' => null,
        ]);
    }

    /**
     * Store new package
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'required|string|max:20|unique:packages,code',
            'description' => 'nullable|string|max:500',
            'speed_download' => 'required|integer|min:128',
            'speed_upload' => 'required|integer|min:128',
            'price' => 'required|numeric|min:0',
            'setup_fee' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
            'mikrotik_profile' => 'nullable|string|max:50',
            'burst_limit' => 'nullable|string|max:50',
            'burst_threshold' => 'nullable|string|max:50',
            'burst_time' => 'nullable|string|max:20',
            'priority' => 'nullable|integer|min:1|max:8',
            'address_list' => 'nullable|string|max:50',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $validated['is_active'] = $validated['is_active'] ?? true;
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        // Generate mikrotik profile name if not provided
        if (empty($validated['mikrotik_profile'])) {
            $validated['mikrotik_profile'] = 'pkg-' . strtolower($validated['code']);
        }

        $package = Package::create($validated);

        return redirect()->route('admin.packages.index')
            ->with('success', 'Paket berhasil ditambahkan');
    }

    /**
     * Show package detail
     */
    public function show(Package $package)
    {
        $package->loadCount('customers');
        $package->load(['customers' => fn($q) => $q->limit(10)->orderBy('created_at', 'desc')]);

        return Inertia::render('Admin/Package/Show', [
            'package' => $package,
        ]);
    }

    /**
     * Show edit form
     */
    public function edit(Package $package)
    {
        return Inertia::render('Admin/Package/Form', [
            'package' => $package,
        ]);
    }

    /**
     * Update package
     */
    public function update(Request $request, Package $package)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'code' => ['required', 'string', 'max:20', Rule::unique('packages')->ignore($package->id)],
            'description' => 'nullable|string|max:500',
            'speed_download' => 'required|integer|min:128',
            'speed_upload' => 'required|integer|min:128',
            'price' => 'required|numeric|min:0',
            'setup_fee' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
            'mikrotik_profile' => 'nullable|string|max:50',
            'burst_limit' => 'nullable|string|max:50',
            'burst_threshold' => 'nullable|string|max:50',
            'burst_time' => 'nullable|string|max:20',
            'priority' => 'nullable|integer|min:1|max:8',
            'address_list' => 'nullable|string|max:50',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $package->update($validated);

        return redirect()->route('admin.packages.index')
            ->with('success', 'Paket berhasil diperbarui');
    }

    /**
     * Delete package
     */
    public function destroy(Package $package)
    {
        // Check if package has customers
        if ($package->customers()->exists()) {
            return back()->with('error', 'Tidak dapat menghapus paket yang masih digunakan pelanggan');
        }

        $package->delete();

        return redirect()->route('admin.packages.index')
            ->with('success', 'Paket berhasil dihapus');
    }

    /**
     * Toggle package active status
     */
    public function toggleActive(Package $package)
    {
        $package->update(['is_active' => !$package->is_active]);

        $status = $package->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return back()->with('success', "Paket berhasil {$status}");
    }
}
