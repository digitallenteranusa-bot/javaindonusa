<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Customer;
use App\Models\Setting;
use App\Services\Notification\NotificationService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BroadcastController extends Controller
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    /**
     * Show maintenance broadcast form
     */
    public function create()
    {
        $areas = Area::active()
            ->withCount(['customers' => function ($q) {
                $q->where('status', 'active');
            }])
            ->orderBy('name')
            ->get()
            ->map(fn ($area) => [
                'id' => $area->id,
                'name' => $area->name,
                'active_customers' => $area->customers_count,
            ]);

        $totalActiveCustomers = Customer::where('status', 'active')->count();

        $maintenanceTemplate = Setting::getValue('notification', 'maintenance_template', '');

        return Inertia::render('Admin/Broadcast/Create', [
            'areas' => $areas,
            'totalActiveCustomers' => $totalActiveCustomers,
            'maintenanceTemplate' => $maintenanceTemplate,
        ]);
    }

    /**
     * Send maintenance notification
     */
    public function sendMaintenance(Request $request)
    {
        $validated = $request->validate([
            'tanggal_mulai' => 'required|string',
            'tanggal_selesai' => 'required|string',
            'keterangan' => 'required|string|max:1000',
            'target' => 'required|in:all,area',
            'area_id' => 'required_if:target,area|nullable|exists:areas,id',
        ], [
            'tanggal_mulai.required' => 'Tanggal mulai wajib diisi.',
            'tanggal_selesai.required' => 'Estimasi selesai wajib diisi.',
            'keterangan.required' => 'Keterangan wajib diisi.',
            'area_id.required_if' => 'Area wajib dipilih jika target per area.',
        ]);

        // Get customer IDs based on target
        $customerIds = null;
        if ($validated['target'] === 'area') {
            $customerIds = Customer::where('status', 'active')
                ->where('area_id', $validated['area_id'])
                ->pluck('id')
                ->toArray();

            if (empty($customerIds)) {
                return back()->with('error', 'Tidak ada pelanggan aktif di area yang dipilih.');
            }
        }

        $result = $this->notificationService->sendMaintenanceNotice(
            $validated['tanggal_mulai'],
            $validated['tanggal_selesai'],
            $validated['keterangan'],
            $customerIds
        );

        return back()->with('success', "Notifikasi maintenance berhasil dikirim ke {$result['sent']} pelanggan.");
    }
}
