<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Radius\Nas;
use App\Models\Radius\RadAcct;
use App\Models\Radius\RadCheck;
use App\Models\RadiusServer;
use App\Services\Radius\RadiusService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class RadiusServerController extends Controller
{
    /**
     * Display Radius Server list
     */
    public function index(Request $request)
    {
        $query = RadiusServer::withCount('routers');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $radiusServers = $query->orderBy('name')
            ->paginate($request->get('per_page', 15))
            ->withQueryString();

        return Inertia::render('Admin/RadiusServer/Index', [
            'radiusServers' => $radiusServers,
            'filters' => $request->only(['search', 'status']),
            'statuses' => RadiusServer::getStatuses(),
        ]);
    }

    /**
     * Show create form
     */
    public function create()
    {
        return Inertia::render('Admin/RadiusServer/Form', [
            'radiusServer' => null,
            'statuses' => RadiusServer::getStatuses(),
        ]);
    }

    /**
     * Store new Radius Server
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'ip_address' => 'required|ip',
            'auth_port' => 'required|integer|min:1|max:65535',
            'acct_port' => 'required|integer|min:1|max:65535',
            'secret' => 'required|string|max:255',
            'status' => ['required', Rule::in(array_keys(RadiusServer::getStatuses()))],
            'notes' => 'nullable|string|max:1000',
        ]);

        RadiusServer::create($validated);

        return redirect()->route('admin.radius-servers.index')
            ->with('success', 'Radius Server berhasil ditambahkan');
    }

    /**
     * Show Radius Server detail
     */
    public function show(RadiusServer $radiusServer)
    {
        $radiusServer->load('routers');

        return Inertia::render('Admin/RadiusServer/Show', [
            'radiusServer' => $radiusServer,
            'statuses' => RadiusServer::getStatuses(),
        ]);
    }

    /**
     * Show edit form
     */
    public function edit(RadiusServer $radiusServer)
    {
        return Inertia::render('Admin/RadiusServer/Form', [
            'radiusServer' => $radiusServer,
            'statuses' => RadiusServer::getStatuses(),
        ]);
    }

    /**
     * Update Radius Server
     */
    public function update(Request $request, RadiusServer $radiusServer)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'ip_address' => 'required|ip',
            'auth_port' => 'required|integer|min:1|max:65535',
            'acct_port' => 'required|integer|min:1|max:65535',
            'secret' => 'nullable|string|max:255',
            'status' => ['required', Rule::in(array_keys(RadiusServer::getStatuses()))],
            'notes' => 'nullable|string|max:1000',
        ]);

        // Don't update secret if empty
        if (empty($validated['secret'])) {
            unset($validated['secret']);
        }

        $radiusServer->update($validated);

        return redirect()->route('admin.radius-servers.index')
            ->with('success', 'Radius Server berhasil diperbarui');
    }

    /**
     * Delete Radius Server
     */
    public function destroy(RadiusServer $radiusServer)
    {
        if ($radiusServer->routers()->exists()) {
            return back()->with('error', 'Tidak dapat menghapus Radius Server yang masih digunakan router');
        }

        $radiusServer->delete();

        return redirect()->route('admin.radius-servers.index')
            ->with('success', 'Radius Server berhasil dihapus');
    }

    /**
     * Test RADIUS connection by checking FreeRADIUS DB status.
     */
    public function testConnection(RadiusServer $radiusServer, RadiusService $radiusService)
    {
        if (!$radiusService->isEnabled()) {
            return back()->with('error', 'Integrasi RADIUS belum diaktifkan. Set RADIUS_ENABLED=true di .env');
        }

        try {
            DB::connection('radius')->getPdo();

            $users = RadCheck::distinct('username')->count('username');
            $nasCount = Nas::count();
            $activeSessions = RadAcct::active()->count();

            $nasForServer = Nas::where('nasname', $radiusServer->ip_address)->exists()
                ? 'terdaftar'
                : 'belum terdaftar di NAS';

            return back()->with('success',
                "RADIUS DB OK — {$users} user, {$nasCount} NAS, {$activeSessions} session aktif. " .
                "Server {$radiusServer->ip_address}: {$nasForServer}"
            );
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal terhubung ke RADIUS DB: ' . $e->getMessage());
        }
    }

    /**
     * Sync all routers to FreeRADIUS NAS table.
     */
    public function syncNas(RadiusService $radiusService)
    {
        if (!$radiusService->isEnabled()) {
            return back()->with('error', 'Integrasi RADIUS belum diaktifkan. Set RADIUS_ENABLED=true di .env');
        }

        $stats = $radiusService->syncAllNas();

        return back()->with('success', "NAS sync selesai: {$stats['synced']} berhasil, {$stats['failed']} gagal");
    }
}
