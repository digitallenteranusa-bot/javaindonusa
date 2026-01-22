<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Odp;
use App\Models\Area;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MappingController extends Controller
{
    /**
     * Display mapping page with map
     */
    public function index(Request $request)
    {
        $areas = Area::active()->orderBy('name')->get(['id', 'name']);

        // Get initial center point (first ODP or customer with coordinates)
        $centerPoint = $this->getCenterPoint();

        return Inertia::render('Admin/Mapping/Index', [
            'areas' => $areas,
            'centerPoint' => $centerPoint,
            'filters' => $request->only(['area_id', 'show_customers', 'show_odps', 'status']),
        ]);
    }

    /**
     * Get customers with coordinates for map
     */
    public function getCustomers(Request $request)
    {
        $query = Customer::select([
            'id',
            'customer_id',
            'name',
            'address',
            'phone',
            'status',
            'latitude',
            'longitude',
            'odp_id',
            'area_id',
            'package_id',
        ])
            ->with(['package:id,name', 'area:id,name', 'odp:id,name,code'])
            ->whereNotNull('latitude')
            ->whereNotNull('longitude');

        if ($request->filled('area_id')) {
            $query->where('area_id', $request->area_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('bounds')) {
            $bounds = $request->bounds;
            $query->whereBetween('latitude', [$bounds['south'], $bounds['north']])
                ->whereBetween('longitude', [$bounds['west'], $bounds['east']]);
        }

        $customers = $query->limit(500)->get()->map(function ($customer) {
            return [
                'id' => $customer->id,
                'customer_id' => $customer->customer_id,
                'name' => $customer->name,
                'address' => $customer->address,
                'phone' => $customer->phone,
                'status' => $customer->status,
                'lat' => (float) $customer->latitude,
                'lng' => (float) $customer->longitude,
                'package' => $customer->package?->name,
                'area' => $customer->area?->name,
                'odp' => $customer->odp ? [
                    'id' => $customer->odp->id,
                    'name' => $customer->odp->name,
                    'code' => $customer->odp->code,
                ] : null,
            ];
        });

        return response()->json(['customers' => $customers]);
    }

    /**
     * Get ODPs with coordinates for map
     */
    public function getOdps(Request $request)
    {
        $query = Odp::select([
            'id',
            'name',
            'code',
            'latitude',
            'longitude',
            'pole_type',
            'capacity',
            'used_ports',
            'area_id',
            'is_active',
        ])
            ->with(['area:id,name'])
            ->whereNotNull('latitude')
            ->whereNotNull('longitude');

        if ($request->filled('area_id')) {
            $query->where('area_id', $request->area_id);
        }

        if ($request->has('active_only')) {
            $query->active();
        }

        if ($request->filled('bounds')) {
            $bounds = $request->bounds;
            $query->whereBetween('latitude', [$bounds['south'], $bounds['north']])
                ->whereBetween('longitude', [$bounds['west'], $bounds['east']]);
        }

        $odps = $query->limit(200)->get()->map(function ($odp) {
            return [
                'id' => $odp->id,
                'name' => $odp->name,
                'code' => $odp->code,
                'lat' => (float) $odp->latitude,
                'lng' => (float) $odp->longitude,
                'pole_type' => $odp->pole_type,
                'pole_type_label' => $odp->pole_type_label,
                'capacity' => $odp->capacity,
                'used_ports' => $odp->used_ports,
                'available_ports' => $odp->available_ports,
                'usage_percentage' => $odp->usage_percentage,
                'area' => $odp->area?->name,
                'is_active' => $odp->is_active,
            ];
        });

        return response()->json(['odps' => $odps]);
    }

    /**
     * Update customer coordinates
     */
    public function updateCustomerLocation(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        $customer->update($validated);

        return response()->json(['success' => true, 'message' => 'Lokasi pelanggan berhasil diperbarui']);
    }

    /**
     * Update ODP coordinates
     */
    public function updateOdpLocation(Request $request, Odp $odp)
    {
        $validated = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        $odp->update($validated);

        return response()->json(['success' => true, 'message' => 'Lokasi ODP berhasil diperbarui']);
    }

    /**
     * Link customer to ODP
     */
    public function linkCustomerToOdp(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'odp_id' => 'required|exists:odps,id',
        ]);

        $odp = Odp::findOrFail($validated['odp_id']);

        // Check if ODP has available ports
        if ($odp->available_ports <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'ODP tidak memiliki port yang tersedia',
            ], 422);
        }

        // Update old ODP if exists
        if ($customer->odp_id && $customer->odp_id != $validated['odp_id']) {
            $oldOdp = Odp::find($customer->odp_id);
            if ($oldOdp) {
                $oldOdp->recalculateUsedPorts();
            }
        }

        $customer->update(['odp_id' => $validated['odp_id']]);

        // Update new ODP used ports
        $odp->recalculateUsedPorts();

        return response()->json(['success' => true, 'message' => 'Pelanggan berhasil dihubungkan ke ODP']);
    }

    /**
     * Unlink customer from ODP
     */
    public function unlinkCustomerFromOdp(Customer $customer)
    {
        $oldOdpId = $customer->odp_id;

        $customer->update(['odp_id' => null]);

        // Update old ODP used ports
        if ($oldOdpId) {
            $odp = Odp::find($oldOdpId);
            if ($odp) {
                $odp->recalculateUsedPorts();
            }
        }

        return response()->json(['success' => true, 'message' => 'Pelanggan berhasil dilepas dari ODP']);
    }

    /**
     * Get center point for initial map view
     */
    protected function getCenterPoint(): array
    {
        // Try to get first ODP with coordinates
        $odp = Odp::whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->first(['latitude', 'longitude']);

        if ($odp) {
            return [
                'lat' => (float) $odp->latitude,
                'lng' => (float) $odp->longitude,
            ];
        }

        // Try to get first customer with coordinates
        $customer = Customer::whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->first(['latitude', 'longitude']);

        if ($customer) {
            return [
                'lat' => (float) $customer->latitude,
                'lng' => (float) $customer->longitude,
            ];
        }

        // Default to Indonesia center (Jakarta)
        return [
            'lat' => -6.2088,
            'lng' => 106.8456,
        ];
    }

    /**
     * Get statistics for dashboard
     */
    public function getStats()
    {
        return response()->json([
            'total_odps' => Odp::count(),
            'active_odps' => Odp::active()->count(),
            'customers_with_location' => Customer::whereNotNull('latitude')->whereNotNull('longitude')->count(),
            'customers_with_odp' => Customer::whereNotNull('odp_id')->count(),
            'odps_with_location' => Odp::whereNotNull('latitude')->whereNotNull('longitude')->count(),
        ]);
    }
}
