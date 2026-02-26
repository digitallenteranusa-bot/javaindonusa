<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Package\StorePackageRequest;
use App\Http\Requests\Admin\Package\UpdatePackageRequest;
use App\Models\Package;
use Illuminate\Http\Request;
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
    public function store(StorePackageRequest $request)
    {
        $validated = $request->validated();

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
    public function update(UpdatePackageRequest $request, Package $package)
    {
        $validated = $request->validated();

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
