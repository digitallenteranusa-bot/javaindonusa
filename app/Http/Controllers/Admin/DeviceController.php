<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerDevice;
use App\Models\Customer;
use App\Services\GenieAcs\GenieAcsService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DeviceController extends Controller
{
    protected GenieAcsService $genieAcs;

    public function __construct(GenieAcsService $genieAcs)
    {
        $this->genieAcs = $genieAcs;
    }

    /**
     * Display a listing of devices.
     */
    public function index(Request $request)
    {
        $query = CustomerDevice::with('customer:id,customer_id,name,phone')
            ->orderBy('last_inform', 'desc');

        // Filters
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('serial_number', 'like', "%{$search}%")
                    ->orWhere('device_id', 'like', "%{$search}%")
                    ->orWhere('wan_ip', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('customer_id', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('status')) {
            if ($request->status === 'online') {
                $query->online();
            } elseif ($request->status === 'offline') {
                $query->offline();
            }
        }

        if ($request->filled('manufacturer')) {
            $query->where('manufacturer', $request->manufacturer);
        }

        if ($request->filled('signal')) {
            if ($request->signal === 'weak') {
                $query->withLowSignal();
            }
        }

        $devices = $query->paginate(20)->withQueryString();

        // Get statistics
        $stats = [
            'total' => CustomerDevice::count(),
            'online' => CustomerDevice::online()->count(),
            'offline' => CustomerDevice::offline()->count(),
            'weak_signal' => CustomerDevice::withLowSignal()->count(),
        ];

        // Get manufacturers for filter
        $manufacturers = CustomerDevice::distinct()
            ->whereNotNull('manufacturer')
            ->pluck('manufacturer')
            ->sort()
            ->values();

        return Inertia::render('Admin/Device/Index', [
            'devices' => $devices,
            'stats' => $stats,
            'manufacturers' => $manufacturers,
            'filters' => $request->only(['search', 'status', 'manufacturer', 'signal']),
        ]);
    }

    /**
     * Display the specified device.
     */
    public function show(CustomerDevice $device)
    {
        $device->load('customer.package');

        // Get fresh data from GenieACS
        $genieData = $this->genieAcs->getDevice($device->device_id);

        // Get customers without device for linking
        $customers = Customer::whereDoesntHave('device')
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'customer_id', 'name']);

        return Inertia::render('Admin/Device/Show', [
            'device' => $device,
            'genieData' => $genieData,
            'customers' => $customers,
        ]);
    }

    /**
     * Link device to customer.
     */
    public function linkCustomer(Request $request, CustomerDevice $device)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
        ]);

        $device->update(['customer_id' => $request->customer_id]);

        // Also update customer's onu_serial if empty
        $customer = Customer::find($request->customer_id);
        if ($customer && !$customer->onu_serial && $device->serial_number) {
            $customer->update(['onu_serial' => $device->serial_number]);
        }

        return back()->with('success', 'Device berhasil dihubungkan dengan pelanggan');
    }

    /**
     * Unlink device from customer.
     */
    public function unlinkCustomer(CustomerDevice $device)
    {
        $device->update(['customer_id' => null]);

        return back()->with('success', 'Device berhasil dilepas dari pelanggan');
    }

    /**
     * Reboot device.
     */
    public function reboot(CustomerDevice $device)
    {
        $result = $this->genieAcs->rebootDevice($device->device_id);

        if ($result) {
            return back()->with('success', 'Perintah reboot berhasil dikirim');
        }

        return back()->with('error', 'Gagal mengirim perintah reboot');
    }

    /**
     * Refresh device parameters.
     */
    public function refresh(CustomerDevice $device)
    {
        $result = $this->genieAcs->refreshDevice($device->device_id);

        if ($result) {
            return back()->with('success', 'Perintah refresh berhasil dikirim');
        }

        return back()->with('error', 'Gagal mengirim perintah refresh');
    }

    /**
     * Factory reset device.
     */
    public function factoryReset(Request $request, CustomerDevice $device)
    {
        $request->validate([
            'confirm' => 'required|in:RESET',
        ]);

        $result = $this->genieAcs->factoryResetDevice($device->device_id);

        if ($result) {
            return back()->with('success', 'Perintah factory reset berhasil dikirim');
        }

        return back()->with('error', 'Gagal mengirim perintah factory reset');
    }

    /**
     * Change WiFi settings.
     */
    public function updateWifi(Request $request, CustomerDevice $device)
    {
        $request->validate([
            'ssid' => 'nullable|string|max:32',
            'password' => 'nullable|string|min:8|max:63',
            'enabled' => 'nullable|boolean',
        ]);

        $success = true;
        $messages = [];

        if ($request->filled('ssid')) {
            if ($this->genieAcs->changeWifiSsid($device->device_id, $request->ssid)) {
                $messages[] = 'SSID diubah';
            } else {
                $success = false;
            }
        }

        if ($request->filled('password')) {
            if ($this->genieAcs->changeWifiPassword($device->device_id, $request->password)) {
                $messages[] = 'Password WiFi diubah';
            } else {
                $success = false;
            }
        }

        if ($request->has('enabled')) {
            if ($this->genieAcs->setWifiEnabled($device->device_id, $request->enabled)) {
                $messages[] = $request->enabled ? 'WiFi diaktifkan' : 'WiFi dinonaktifkan';
            } else {
                $success = false;
            }
        }

        if ($success && !empty($messages)) {
            return back()->with('success', implode(', ', $messages));
        }

        return back()->with('error', 'Gagal mengubah pengaturan WiFi');
    }

    /**
     * Sync all devices from GenieACS.
     */
    public function sync()
    {
        $result = $this->genieAcs->syncAllDevices();

        if ($result['success']) {
            return back()->with('success', "Berhasil sync {$result['synced']} device ({$result['created']} baru, {$result['updated']} update)");
        }

        return back()->with('error', 'Gagal sync device dari GenieACS');
    }

    /**
     * Check GenieACS connection status.
     */
    public function status()
    {
        $connection = $this->genieAcs->checkConnection();
        $devices = $this->genieAcs->getDevices();

        return response()->json([
            'connected' => $connection['success'],
            'message' => $connection['message'],
            'device_count' => $devices ? count($devices) : 0,
            'local_count' => CustomerDevice::count(),
            'online_count' => CustomerDevice::online()->count(),
        ]);
    }

    /**
     * Delete device from local database.
     */
    public function destroy(CustomerDevice $device)
    {
        $device->delete();

        return redirect()
            ->route('admin.devices.index')
            ->with('success', 'Device berhasil dihapus dari database lokal');
    }

    /**
     * Get available firmware files from GenieACS.
     */
    public function getFirmwareFiles()
    {
        $files = $this->genieAcs->getFiles();

        // Filter only firmware files
        $firmwareFiles = collect($files ?? [])->filter(function ($file) {
            return isset($file['metadata']['fileType']) &&
                str_contains($file['metadata']['fileType'], 'Firmware');
        })->values();

        return response()->json([
            'files' => $firmwareFiles,
        ]);
    }

    /**
     * Install firmware update to device.
     */
    public function installUpdate(Request $request, CustomerDevice $device)
    {
        $request->validate([
            'file_id' => 'required|string',
        ]);

        $fileId = $request->file_id;

        // Get file info from GenieACS
        $file = $this->genieAcs->getFile($fileId);

        if (!$file) {
            return back()->with('error', 'File firmware tidak ditemukan');
        }

        // Build download URL using FS (File Server) URL
        $fsUrl = rtrim(config('genieacs.fs_url'), '/');
        $fileUrl = $fsUrl . '/' . urlencode($fileId);

        // Send download task to device
        $result = $this->genieAcs->downloadFirmware($device->device_id, $fileUrl);

        if ($result) {
            return back()->with('success', 'Perintah install firmware berhasil dikirim. Device akan restart setelah selesai.');
        }

        return back()->with('error', 'Gagal mengirim perintah install firmware');
    }

    /**
     * Upload firmware file to GenieACS.
     */
    public function uploadFirmware(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:102400', // Max 100MB
            'version' => 'nullable|string|max:50',
            'oui' => 'nullable|string|max:20',
            'product_class' => 'nullable|string|max:100',
        ]);

        $uploadedFile = $request->file('file');
        $filename = $uploadedFile->getClientOriginalName();
        $content = file_get_contents($uploadedFile->getRealPath());

        $metadata = [
            'version' => $request->version ?? '',
            'oui' => $request->oui ?? '',
            'productClass' => $request->product_class ?? '',
        ];

        $result = $this->genieAcs->uploadFile($filename, $content, '1 Firmware Upgrade Image', $metadata);

        if ($result) {
            return back()->with('success', 'Firmware berhasil diupload ke GenieACS');
        }

        return back()->with('error', 'Gagal mengupload firmware');
    }

    /**
     * Delete firmware file from GenieACS.
     */
    public function deleteFirmware(Request $request)
    {
        $request->validate([
            'file_id' => 'required|string',
        ]);

        $result = $this->genieAcs->deleteFile($request->file_id);

        if ($result) {
            return back()->with('success', 'Firmware berhasil dihapus');
        }

        return back()->with('error', 'Gagal menghapus firmware');
    }

    /**
     * Get pending tasks for a device.
     */
    public function getTasks(CustomerDevice $device)
    {
        $tasks = $this->genieAcs->getTasks($device->device_id);

        return response()->json([
            'tasks' => $tasks ?? [],
        ]);
    }
}
