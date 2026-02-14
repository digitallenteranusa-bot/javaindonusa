<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Services\Customer\CustomerPortalService;
use App\Services\Payment\TripayService;
use App\Models\Customer;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PortalController extends Controller
{
    protected CustomerPortalService $portalService;
    protected TripayService $tripayService;

    public function __construct(CustomerPortalService $portalService, TripayService $tripayService)
    {
        $this->portalService = $portalService;
        $this->tripayService = $tripayService;
    }

    // ================================================================
    // LOGIN
    // ================================================================

    /**
     * Halaman login pelanggan
     */
    public function showLogin()
    {
        return Inertia::render('Customer/Login', [
            'isp_info' => $this->portalService->getIspInfo(),
        ]);
    }

    /**
     * Request OTP
     */
    public function requestOTP(Request $request)
    {
        $request->validate([
            'phone' => 'required|string|min:10|max:15',
        ]);

        $result = $this->portalService->requestLogin($request->phone);

        if (!$result['success']) {
            return back()->with('error', $result['message']);
        }

        // Simpan data di session untuk halaman verify OTP
        session([
            'otp_phone' => $request->phone,
            'otp_phone_masked' => $result['phone_masked'],
        ]);

        return redirect()->route('customer.show-verify-otp')
            ->with('success', $result['message']);
    }

    /**
     * Tampilkan halaman input OTP
     */
    public function showVerifyOTP()
    {
        $phone = session('otp_phone');
        $phoneMasked = session('otp_phone_masked');

        // Jika tidak ada data OTP di session, kembali ke login
        if (!$phone) {
            return redirect()->route('customer.login')
                ->with('error', 'Silakan masukkan nomor HP terlebih dahulu');
        }

        return Inertia::render('Customer/VerifyOTP', [
            'phone' => $phone,
            'phone_masked' => $phoneMasked,
        ]);
    }

    /**
     * Verifikasi OTP
     */
    public function verifyOTP(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
            'otp' => 'required|string|size:6',
        ]);

        $result = $this->portalService->verifyOTP($request->phone, $request->otp);

        if (!$result['success']) {
            return back()->with('error', $result['message']);
        }

        // Simpan token di session
        session(['customer_token' => $result['token']]);
        session(['customer_id' => $result['customer']->id]);

        return redirect()->route('customer.dashboard');
    }

    /**
     * Login via token (dari link di SMS/WA)
     */
    public function loginWithToken(string $token)
    {
        $customer = $this->portalService->loginWithToken($token);

        if (!$customer) {
            return redirect()->route('customer.login')
                ->with('error', 'Link login tidak valid atau sudah kadaluarsa');
        }

        session(['customer_token' => $token]);
        session(['customer_id' => $customer->id]);

        return redirect()->route('customer.dashboard');
    }

    /**
     * Logout
     */
    public function logout(Request $request)
    {
        $token = session('customer_token');

        if ($token) {
            $this->portalService->logout($token);
        }

        session()->forget(['customer_token', 'customer_id']);

        return redirect()->route('customer.login');
    }

    // ================================================================
    // DASHBOARD
    // ================================================================

    /**
     * Dashboard pelanggan
     */
    public function dashboard()
    {
        $customer = $this->getAuthenticatedCustomer();

        if (!$customer) {
            return redirect()->route('customer.login');
        }

        $data = $this->portalService->getDashboardData($customer);

        // URL untuk kirim bukti transfer
        $data['transfer_proof_wa_url'] = $this->portalService->getTransferProofWhatsAppUrl($customer);

        // Tripay online payment flag
        $data['tripay_enabled'] = $this->tripayService->isEnabled();

        return Inertia::render('Customer/Dashboard', $data);
    }

    /**
     * Halaman histori tagihan
     */
    public function invoices()
    {
        $customer = $this->getAuthenticatedCustomer();

        if (!$customer) {
            return redirect()->route('customer.login');
        }

        $invoices = $customer->invoices()
            ->orderBy('period_year', 'desc')
            ->orderBy('period_month', 'desc')
            ->paginate(12);

        return Inertia::render('Customer/Invoices', [
            'customer' => $customer,
            'invoices' => $invoices,
            'isp_info' => $this->portalService->getIspInfo(),
        ]);
    }

    /**
     * Halaman histori pembayaran
     */
    public function payments()
    {
        $customer = $this->getAuthenticatedCustomer();

        if (!$customer) {
            return redirect()->route('customer.login');
        }

        $payments = $customer->payments()
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return Inertia::render('Customer/Payments', [
            'customer' => $customer,
            'payments' => $payments,
        ]);
    }

    /**
     * Halaman info pembayaran (rekening, kontak)
     */
    public function paymentInfo()
    {
        $customer = $this->getAuthenticatedCustomer();

        if (!$customer) {
            return redirect()->route('customer.login');
        }

        $ispInfo = $this->portalService->getIspInfo();
        $transferProofUrl = $this->portalService->getTransferProofWhatsAppUrl($customer);

        return Inertia::render('Customer/PaymentInfo', [
            'customer' => $customer,
            'isp_info' => $ispInfo,
            'transfer_proof_wa_url' => $transferProofUrl,
        ]);
    }

    // ================================================================
    // HALAMAN ISOLIR (Public)
    // ================================================================

    /**
     * Halaman info isolir (tanpa login)
     */
    public function isolationPage(string $customerId)
    {
        $customer = Customer::where('customer_id', $customerId)->first();

        if (!$customer) {
            abort(404, 'Pelanggan tidak ditemukan');
        }

        // Hanya tampilkan jika status isolated
        if ($customer->status !== 'isolated') {
            return redirect()->route('customer.login');
        }

        $ispInfo = $this->portalService->getIspInfo();

        return Inertia::render('Customer/IsolationPage', [
            'customer' => [
                'customer_id' => $customer->customer_id,
                'name' => $customer->name,
                'total_debt' => $customer->total_debt,
                'isolation_reason' => $customer->isolation_reason,
            ],
            'isp_info' => $ispInfo,
            'transfer_proof_wa_url' => $this->portalService->getTransferProofWhatsAppUrl($customer),
        ]);
    }

    // ================================================================
    // HELPER
    // ================================================================

    protected function getAuthenticatedCustomer(): ?Customer
    {
        $customerId = session('customer_id');
        $token = session('customer_token');

        if (!$customerId || !$token) {
            return null;
        }

        return Customer::find($customerId);
    }
}
