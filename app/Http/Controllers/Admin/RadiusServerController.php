<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RadiusServer;
use Illuminate\Http\Request;
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
     * Test Radius Server connection (placeholder)
     */
    public function testConnection(RadiusServer $radiusServer)
    {
        // This is a placeholder - actual RADIUS testing requires RADIUS client library
        // For now, just do a basic port check

        $socket = @fsockopen($radiusServer->ip_address, $radiusServer->auth_port, $errno, $errstr, 5);

        if ($socket) {
            fclose($socket);
            return back()->with('success', 'Port Radius dapat dijangkau (test koneksi penuh belum tersedia)');
        }

        return back()->with('error', "Tidak dapat terhubung ke port {$radiusServer->auth_port}: {$errstr}");
    }
}
