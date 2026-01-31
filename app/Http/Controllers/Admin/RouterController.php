<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Router;
use App\Services\Mikrotik\MikrotikService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class RouterController extends Controller
{
    protected MikrotikService $mikrotikService;

    public function __construct(MikrotikService $mikrotikService)
    {
        $this->mikrotikService = $mikrotikService;
    }

    /**
     * Display router list
     */
    public function index(Request $request)
    {
        $query = Router::withCount(['customers']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%")
                    ->orWhere('identity', 'like', "%{$search}%");
            });
        }

        if ($request->has('active_only')) {
            $query->active();
        }

        $routers = $query->orderBy('name')->paginate($request->get('per_page', 15))
            ->withQueryString();

        return Inertia::render('Admin/Router/Index', [
            'routers' => $routers,
            'filters' => $request->only(['search', 'active_only']),
        ]);
    }

    /**
     * Show create form
     */
    public function create()
    {
        return Inertia::render('Admin/Router/Form', [
            'router' => null,
        ]);
    }

    /**
     * Store new router
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'ip_address' => 'required|ip|unique:routers,ip_address',
            'api_port' => 'required|integer|min:1|max:65535',
            'username' => 'required|string|max:50',
            'password' => 'required|string|max:100',
            'is_active' => 'boolean',
            'notes' => 'nullable|string|max:1000',
        ]);

        $validated['is_active'] = $validated['is_active'] ?? true;

        $router = Router::create($validated);

        return redirect()->route('admin.routers.index')
            ->with('success', 'Router berhasil ditambahkan');
    }

    /**
     * Show router detail
     */
    public function show(Router $router)
    {
        $router->loadCount(['customers']);
        $router->load(['customers' => fn($q) => $q->limit(10)->orderBy('created_at', 'desc')]);

        return Inertia::render('Admin/Router/Show', [
            'router' => $router,
        ]);
    }

    /**
     * Show edit form
     */
    public function edit(Router $router)
    {
        return Inertia::render('Admin/Router/Form', [
            'router' => $router,
        ]);
    }

    /**
     * Update router
     */
    public function update(Request $request, Router $router)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'ip_address' => ['required', 'ip', Rule::unique('routers')->ignore($router->id)],
            'api_port' => 'required|integer|min:1|max:65535',
            'username' => 'required|string|max:50',
            'password' => 'nullable|string|max:100',
            'is_active' => 'boolean',
            'notes' => 'nullable|string|max:1000',
        ]);

        // Only update password if provided
        if (empty($validated['password'])) {
            unset($validated['password']);
        }

        $router->update($validated);

        return redirect()->route('admin.routers.index')
            ->with('success', 'Router berhasil diperbarui');
    }

    /**
     * Delete router
     */
    public function destroy(Router $router)
    {
        // Check if router has customers
        $customerCount = $router->customers()->count();
        if ($customerCount > 0) {
            return back()->with('error', "Tidak dapat menghapus router yang masih memiliki {$customerCount} pelanggan. Pindahkan pelanggan ke router lain terlebih dahulu.");
        }

        $routerName = $router->name;
        $router->delete();

        return redirect()->route('admin.routers.index')
            ->with('success', "Router '{$routerName}' berhasil dihapus");
    }

    /**
     * Test router connection
     */
    public function testConnection(Router $router)
    {
        try {
            $this->mikrotikService->connect($router);
            $identity = $this->mikrotikService->getIdentity();

            $router->update([
                'last_connected_at' => now(),
                'identity' => $identity,
            ]);

            return back()->with('success', "Koneksi berhasil! Router identity: {$identity}");

        } catch (\Exception $e) {
            return back()->with('error', 'Gagal terhubung ke router: ' . $e->getMessage());
        }
    }

    /**
     * Sync router info (identity, version, resources)
     */
    public function syncInfo(Router $router)
    {
        try {
            $this->mikrotikService->connect($router);

            // Use getRouterInfo() which fetches from both /system/resource and /system/routerboard
            $info = $this->mikrotikService->getRouterInfo();

            $router->update([
                'identity' => $info['identity'] ?? null,
                'version' => $info['version'] ?? null,
                'model' => $info['model'] ?? $info['board_name'] ?? null,
                'serial_number' => $info['serial'] ?? null,
                'uptime' => $info['uptime'] ?? null,
                'cpu_load' => $info['cpu_load'] ?? null,
                'memory_usage' => isset($info['free_memory'], $info['total_memory']) && $info['total_memory'] > 0
                    ? round((1 - $info['free_memory'] / $info['total_memory']) * 100)
                    : null,
                'last_connected_at' => now(),
            ]);

            $modelInfo = $info['model'] ?? $info['board_name'] ?? 'Unknown';
            $versionInfo = $info['version'] ?? 'Unknown';

            return back()->with('success', "Informasi router berhasil disinkronkan. Model: {$modelInfo}, Version: {$versionInfo}");

        } catch (\Exception $e) {
            return back()->with('error', 'Gagal sinkronisasi: ' . $e->getMessage());
        }
    }

    /**
     * Toggle router active status
     */
    public function toggleActive(Router $router)
    {
        $router->update(['is_active' => !$router->is_active]);

        $status = $router->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return back()->with('success', "Router berhasil {$status}");
    }
}
