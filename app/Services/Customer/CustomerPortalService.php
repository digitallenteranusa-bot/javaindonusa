<?php

namespace App\Services\Customer;

use App\Models\Customer;
use App\Models\CustomerToken;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\IspInfo;
use App\Services\Notification\NotificationService;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;

class CustomerPortalService
{
    protected NotificationService $notification;

    public function __construct(NotificationService $notification)
    {
        $this->notification = $notification;
    }

    // ================================================================
    // LOGIN VIA NOMOR HP (TANPA PASSWORD)
    // ================================================================

    /**
     * Request login - kirim OTP via WhatsApp
     */
    public function requestLogin(string $phone): array
    {
        // Normalize phone number
        $phone = $this->normalizePhone($phone);

        // Cari customer berdasarkan nomor HP
        $customer = Customer::where('phone', $phone)
            ->orWhere('phone', $this->formatPhoneAlternative($phone))
            ->first();

        if (!$customer) {
            return [
                'success' => false,
                'message' => 'Nomor HP tidak terdaftar',
            ];
        }

        // Generate OTP (6 digit)
        $otp = $this->generateOTP();

        // Kirim OTP via WhatsApp terlebih dahulu
        $sendResult = $this->sendOTPViaWhatsApp($customer, $otp);

        if (!$sendResult['success']) {
            return [
                'success' => false,
                'message' => 'Gagal mengirim OTP: ' . ($sendResult['message'] ?? 'WhatsApp tidak tersedia'),
            ];
        }

        // Simpan OTP dengan expiry 5 menit (hanya jika berhasil kirim)
        $token = CustomerToken::updateOrCreate(
            ['customer_id' => $customer->id],
            [
                'token' => Str::random(64),
                'otp_code' => $otp,
                'otp_expires_at' => now()->addMinutes(5),
                'expires_at' => now()->addHours(24),
            ]
        );

        return [
            'success' => true,
            'message' => 'Kode OTP telah dikirim ke WhatsApp Anda',
            'customer_id' => $customer->customer_id,
            'phone_masked' => $this->maskPhone($phone),
        ];
    }

    /**
     * Verifikasi OTP dan login
     */
    public function verifyOTP(string $phone, string $otp): array
    {
        $phone = $this->normalizePhone($phone);

        $customer = Customer::where('phone', $phone)
            ->orWhere('phone', $this->formatPhoneAlternative($phone))
            ->first();

        if (!$customer) {
            return [
                'success' => false,
                'message' => 'Nomor HP tidak terdaftar',
            ];
        }

        $token = CustomerToken::where('customer_id', $customer->id)
            ->where('otp_code', $otp)
            ->where('otp_expires_at', '>', now())
            ->first();

        if (!$token) {
            return [
                'success' => false,
                'message' => 'Kode OTP tidak valid atau sudah kadaluarsa',
            ];
        }

        // Clear OTP setelah digunakan
        $token->update([
            'otp_code' => null,
            'otp_expires_at' => null,
            'last_used_at' => now(),
        ]);

        return [
            'success' => true,
            'token' => $token->token,
            'customer' => $customer,
            'expires_at' => $token->expires_at,
        ];
    }

    /**
     * Login langsung tanpa OTP (untuk link dari SMS/WA)
     */
    public function loginWithToken(string $token): ?Customer
    {
        $customerToken = CustomerToken::where('token', $token)
            ->where('expires_at', '>', now())
            ->first();

        if (!$customerToken) {
            return null;
        }

        $customerToken->update(['last_used_at' => now()]);

        return $customerToken->customer;
    }

    /**
     * Logout - hapus token
     */
    public function logout(string $token): bool
    {
        return CustomerToken::where('token', $token)->delete() > 0;
    }

    // ================================================================
    // DASHBOARD PELANGGAN
    // ================================================================

    /**
     * Ambil data dashboard pelanggan
     */
    public function getDashboardData(Customer $customer): array
    {
        return [
            'customer' => $this->getCustomerInfo($customer),
            'billing' => $this->getBillingInfo($customer),
            'invoices' => $this->getInvoiceHistory($customer),
            'payments' => $this->getPaymentHistory($customer),
            'isp_info' => $this->getIspInfo(),
        ];
    }

    /**
     * Info pelanggan
     */
    protected function getCustomerInfo(Customer $customer): array
    {
        $customer->load('package');

        return [
            'customer_id' => $customer->customer_id,
            'name' => $customer->name,
            'address' => $customer->address,
            'phone' => $customer->phone,
            'package' => $customer->package ? [
                'name' => $customer->package->name,
                'speed' => ($customer->package->speed_download / 1024) . ' Mbps',
                'price' => $customer->package->price,
            ] : null,
            'status' => $customer->status,
            'credit_balance' => $customer->credit_balance,
            'join_date' => Carbon::parse($customer->join_date)->format('d F Y'),
        ];
    }

    /**
     * Info tagihan
     */
    protected function getBillingInfo(Customer $customer): array
    {
        $currentMonth = now()->month;
        $currentYear = now()->year;

        // Invoice bulan ini
        $currentInvoice = Invoice::where('customer_id', $customer->id)
            ->where('period_year', $currentYear)
            ->where('period_month', $currentMonth)
            ->first();

        // Total hutang
        $totalDebt = $customer->total_debt;

        // Invoice belum bayar
        $unpaidInvoices = Invoice::where('customer_id', $customer->id)
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->orderBy('period_year')
            ->orderBy('period_month')
            ->get();

        $packagePrice = $customer->package?->price ?? 0;

        return [
            'total_debt' => $totalDebt,
            'current_month' => [
                'status' => $currentInvoice?->status ?? 'not_generated',
                'amount' => $currentInvoice?->total_amount ?? $packagePrice,
                'paid_amount' => $currentInvoice?->paid_amount ?? 0,
                'remaining' => $currentInvoice?->remaining_amount ?? $packagePrice,
                'due_date' => $currentInvoice?->due_date,
            ],
            'unpaid_months' => $unpaidInvoices->count(),
            'unpaid_invoices' => $unpaidInvoices->map(fn($inv) => [
                'period' => $this->formatPeriod($inv->period_month, $inv->period_year),
                'amount' => $inv->total_amount,
                'remaining' => $inv->remaining_amount,
                'status' => $inv->status,
                'due_date' => $inv->due_date,
            ]),
            'isolation_status' => $customer->status === 'isolated',
            'isolation_reason' => $customer->isolation_reason,
        ];
    }

    /**
     * Histori invoice (12 bulan terakhir)
     */
    protected function getInvoiceHistory(Customer $customer, int $limit = 12): array
    {
        $invoices = Invoice::where('customer_id', $customer->id)
            ->orderBy('period_year', 'desc')
            ->orderBy('period_month', 'desc')
            ->limit($limit)
            ->get();

        return $invoices->map(fn($inv) => [
            'invoice_number' => $inv->invoice_number,
            'period' => $this->formatPeriod($inv->period_month, $inv->period_year),
            'amount' => $inv->total_amount,
            'paid_amount' => $inv->paid_amount,
            'remaining' => $inv->remaining_amount,
            'status' => $inv->status,
            'due_date' => $inv->due_date,
            'paid_at' => $inv->paid_at,
        ])->toArray();
    }

    /**
     * Histori pembayaran (10 terakhir)
     */
    protected function getPaymentHistory(Customer $customer, int $limit = 10): array
    {
        $payments = Payment::where('customer_id', $customer->id)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return $payments->map(fn($pay) => [
            'payment_number' => $pay->payment_number,
            'date' => Carbon::parse($pay->created_at)->format('d M Y H:i'),
            'amount' => $pay->amount,
            'method' => $pay->payment_method,
            'channel' => $pay->payment_channel,
        ])->toArray();
    }

    /**
     * Info ISP (rekening, kontak, dll)
     */
    public function getIspInfo(): array
    {
        $info = IspInfo::first();

        if (!$info) {
            return [];
        }

        return [
            'company_name' => $info->company_name,
            'tagline' => $info->tagline,
            'phone' => $info->phone_primary,
            'whatsapp' => $info->whatsapp_number,
            'email' => $info->email,
            'address' => $info->address,
            'bank_accounts' => is_string($info->bank_accounts)
                ? json_decode($info->bank_accounts, true)
                : $info->bank_accounts,
            'ewallet_accounts' => is_string($info->ewallet_accounts)
                ? json_decode($info->ewallet_accounts, true)
                : $info->ewallet_accounts,
            'operational_hours' => $info->operational_hours,
        ];
    }

    // ================================================================
    // HELPER METHODS
    // ================================================================

    protected function generateOTP(): string
    {
        return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    protected function normalizePhone(string $phone): string
    {
        // Hapus semua karakter non-digit
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Konversi 62 ke 0
        if (str_starts_with($phone, '62')) {
            $phone = '0' . substr($phone, 2);
        }

        return $phone;
    }

    protected function formatPhoneAlternative(string $phone): string
    {
        // Jika dimulai dengan 0, ubah ke 62
        if (str_starts_with($phone, '0')) {
            return '62' . substr($phone, 1);
        }
        // Jika dimulai dengan 62, ubah ke 0
        if (str_starts_with($phone, '62')) {
            return '0' . substr($phone, 2);
        }
        return $phone;
    }

    protected function maskPhone(string $phone): string
    {
        $length = strlen($phone);
        if ($length < 8) return $phone;

        $start = substr($phone, 0, 4);
        $end = substr($phone, -3);
        $middle = str_repeat('*', $length - 7);

        return $start . $middle . $end;
    }

    protected function formatPeriod(int $month, int $year): string
    {
        $months = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        return $months[$month] . ' ' . $year;
    }

    protected function sendOTPViaWhatsApp(Customer $customer, string $otp): array
    {
        $companyName = IspInfo::first()?->company_name ?? 'ISP';

        $message = "🔐 *KODE OTP*\n\n" .
            "Kode OTP Login Portal Pelanggan: *{$otp}*\n\n" .
            "Kode ini berlaku selama 5 menit.\n" .
            "Jangan berikan kode ini kepada siapapun.\n\n" .
            "_{$companyName}_";

        return $this->notification->sendWhatsApp($customer->phone, $message);
    }

    /**
     * Generate URL untuk kirim bukti transfer via WhatsApp
     */
    public function getTransferProofWhatsAppUrl(Customer $customer): string
    {
        $ispInfo = IspInfo::first();

        if (!$ispInfo || !$ispInfo->whatsapp_number) {
            return '#';
        }

        $whatsappNumber = preg_replace('/[^0-9]/', '', $ispInfo->whatsapp_number);

        $message = "Halo, saya {$customer->name} (ID: {$customer->customer_id})\n\n" .
            "Saya ingin mengirimkan bukti transfer pembayaran internet.\n\n" .
            "[Silakan lampirkan foto bukti transfer]";

        return "https://wa.me/{$whatsappNumber}?text=" . urlencode($message);
    }
}
