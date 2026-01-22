<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Olt;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'ip_address' => 'required|ip|unique:olts,ip_address',
            'type' => ['required', Rule::in(array_keys(Olt::getTypes()))],
            'pon_ports' => ['required', Rule::in(array_keys(Olt::getPonPortOptions()))],
            'username' => 'nullable|string|max:100',
            'password' => 'nullable|string|max:100',
            'telnet_port' => 'required|integer|min:1|max:65535',
            'ssh_port' => 'required|integer|min:1|max:65535',
            'snmp_community' => 'nullable|string|max:100',
            'status' => ['required', Rule::in(array_keys(Olt::getStatuses()))],
            'notes' => 'nullable|string|max:1000',
            'firmware_version' => 'nullable|string|max:100',
        ]);

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
    public function update(Request $request, Olt $olt)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'ip_address' => ['required', 'ip', Rule::unique('olts')->ignore($olt->id)],
            'type' => ['required', Rule::in(array_keys(Olt::getTypes()))],
            'pon_ports' => ['required', Rule::in(array_keys(Olt::getPonPortOptions()))],
            'username' => 'nullable|string|max:100',
            'password' => 'nullable|string|max:100',
            'telnet_port' => 'required|integer|min:1|max:65535',
            'ssh_port' => 'required|integer|min:1|max:65535',
            'snmp_community' => 'nullable|string|max:100',
            'status' => ['required', Rule::in(array_keys(Olt::getStatuses()))],
            'notes' => 'nullable|string|max:1000',
            'firmware_version' => 'nullable|string|max:100',
        ]);

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
    public function updateStatus(Request $request, Olt $olt)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(array_keys(Olt::getStatuses()))],
        ]);

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
