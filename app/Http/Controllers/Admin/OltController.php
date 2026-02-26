<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Olt\StoreOltRequest;
use App\Http\Requests\Admin\Olt\UpdateOltRequest;
use App\Http\Requests\Admin\Olt\UpdateOltStatusRequest;
use App\Models\Olt;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OltController extends Controller
{
    /**
     * Display OLT list
     */
    public function index(Request $request)
    {
        $query = Olt::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%");
            });
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $olts = $query->orderBy('name')
            ->paginate($request->get('per_page', 15))
            ->withQueryString();

        return Inertia::render('Admin/Olt/Index', [
            'olts' => $olts,
            'filters' => $request->only(['search', 'type', 'status']),
            'types' => Olt::getTypes(),
            'statuses' => Olt::getStatuses(),
            'ponPortOptions' => Olt::getPonPortOptions(),
        ]);
    }

    /**
     * Show create form
     */
    public function create()
    {
        return Inertia::render('Admin/Olt/Form', [
            'olt' => null,
            'types' => Olt::getTypes(),
            'statuses' => Olt::getStatuses(),
            'ponPortOptions' => Olt::getPonPortOptions(),
        ]);
    }

    /**
     * Store new OLT
     */
    public function store(StoreOltRequest $request)
    {
        $validated = $request->validated();

        Olt::create($validated);

        return redirect()->route('admin.olts.index')
            ->with('success', 'OLT berhasil ditambahkan');
    }

    /**
     * Show OLT detail
     */
    public function show(Olt $olt)
    {
        return Inertia::render('Admin/Olt/Show', [
            'olt' => $olt,
            'types' => Olt::getTypes(),
            'statuses' => Olt::getStatuses(),
            'ponPortOptions' => Olt::getPonPortOptions(),
        ]);
    }

    /**
     * Show edit form
     */
    public function edit(Olt $olt)
    {
        return Inertia::render('Admin/Olt/Form', [
            'olt' => $olt,
            'types' => Olt::getTypes(),
            'statuses' => Olt::getStatuses(),
            'ponPortOptions' => Olt::getPonPortOptions(),
        ]);
    }

    /**
     * Update OLT
     */
    public function update(UpdateOltRequest $request, Olt $olt)
    {
        $validated = $request->validated();

        // Don't update password if empty
        if (empty($validated['password'])) {
            unset($validated['password']);
        }

        $olt->update($validated);

        return redirect()->route('admin.olts.index')
            ->with('success', 'OLT berhasil diperbarui');
    }

    /**
     * Delete OLT
     */
    public function destroy(Olt $olt)
    {
        $olt->delete();

        return redirect()->route('admin.olts.index')
            ->with('success', 'OLT berhasil dihapus');
    }

    /**
     * Update OLT status
     */
    public function updateStatus(UpdateOltStatusRequest $request, Olt $olt)
    {
        $validated = $request->validated();

        $olt->update($validated);

        return back()->with('success', 'Status OLT berhasil diperbarui');
    }

    /**
     * Check OLT connection
     */
    public function checkConnection(Olt $olt)
    {
        // Basic ping test
        $pingResult = @fsockopen($olt->ip_address, $olt->telnet_port, $errno, $errstr, 5);

        if ($pingResult) {
            fclose($pingResult);
            $olt->update(['last_checked_at' => now()]);

            return back()->with('success', 'OLT dapat dijangkau');
        }

        return back()->with('error', "Tidak dapat terhubung ke OLT: {$errstr}");
    }
}
