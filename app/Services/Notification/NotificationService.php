<?php

namespace App\Services\Notification;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\IspInfo;
use App\Models\Setting;
use App\Models\BillingLog;
use App\Services\Notification\Channels\WhatsAppChannel;
use App\Jobs\SendNotificationJob;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    protected WhatsAppChannel $whatsapp;
    protected ?IspInfo $ispInfo;

    public function __construct(WhatsAppChannel $whatsapp)
    {
        $this->whatsapp = $whatsapp;
        $this->ispInfo = IspInfo::getCached();
    }

    // ================================================================
    // MAIN SEND METHODS
    // ================================================================

    /**
     * Send WhatsApp message
     */
    public function sendWhatsApp(string $phone, string $message, ?array $options = []): array
    {
        try {
            if (!$this->isWhatsAppEnabled()) {
                return ['success' => false, 'message' => 'WhatsApp notifications disabled'];
            }

            $phone = $this->normalizePhone($phone);
            $result = $this->whatsapp->send($phone, $message, $options);

            $this->logNotification('whatsapp', $phone, $message, $result['success']);

            return $result;
        } catch (\Exception $e) {
            Log::error('WhatsApp send failed', [
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Send Email
     */
    public function sendEmail(string $email, string $subject, string $body, ?array $attachments = []): array
    {
        try {
            if (!$this->isEmailEnabled()) {
                return ['success' => false, 'message' => 'Email notifications disabled'];
            }

            Mail::raw($body, function ($mail) use ($email, $subject, $attachments) {
                $mail->to($email)
                    ->subject($subject);

                foreach ($attachments as $attachment) {
                    $mail->attach($attachment);
                }
            });

            $this->logNotification('email', $email, $subject, true);

            return ['success' => true, 'message' => 'Email sent successfully'];
        } catch (\Exception $e) {
            Log::error('Email send failed', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Send notification via preferred channel (async via queue)
     */
    public function sendAsync(
        string $channel,
        string $recipient,
        string $message,
        ?string $subject = null,
        ?array $options = []
    ): void {
        SendNotificationJob::dispatch($channel, $recipient, $message, $subject, $options);
    }

    // ================================================================
    // BILLING NOTIFICATIONS
    // ================================================================

    /**
     * Send invoice notification to customer
     * TIDAK kirim jika: pelanggan tidak punya hutang, atau invoice bulan berjalan
     */
    public function sendInvoiceNotification(Customer $customer, Invoice $invoice): array
    {
        // Jangan kirim jika pelanggan tidak punya hutang
        if ($customer->total_debt <= 0) {
            return ['success' => false, 'message' => 'Customer has no debt, notification skipped'];
        }

        // Jangan kirim notifikasi untuk invoice bulan berjalan (baru di-generate)
        $currentMonth = now()->month;
        $currentYear = now()->year;
        if ($invoice->period_month == $currentMonth && $invoice->period_year == $currentYear) {
            return ['success' => false, 'message' => 'Current month invoice, notification skipped'];
        }

        $message = $this->buildInvoiceMessage($customer, $invoice);

        return $this->sendWhatsApp($customer->phone, $message);
    }

    /**
     * Send payment reminder
     */
    public function sendPaymentReminder(Customer $customer, int $daysBeforeDue = 3): array
    {
        // Jangan kirim jika tidak punya hutang
        if ($customer->total_debt <= 0) {
            return ['success' => false, 'message' => 'Customer has no debt, notification skipped'];
        }

        $message = $this->buildReminderMessage($customer, $daysBeforeDue);

        return $this->sendWhatsApp($customer->phone, $message);
    }

    /**
     * Send overdue notice
     */
    public function sendOverdueNotice(Customer $customer): array
    {
        // Jangan kirim jika tidak punya hutang
        if ($customer->total_debt <= 0) {
            return ['success' => false, 'message' => 'Customer has no debt, notification skipped'];
        }

        $message = $this->buildOverdueMessage($customer);

        return $this->sendWhatsApp($customer->phone, $message);
    }

    /**
     * Send severe overdue notice (tunggakan lebih dari 3 bulan)
     */
    public function sendSevereOverdueNotice(Customer $customer): array
    {
        // Jangan kirim jika tidak punya hutang
        if ($customer->total_debt <= 0) {
            return ['success' => false, 'message' => 'Customer has no debt, notification skipped'];
        }

        // Hitung jumlah bulan tunggakan
        $overdueMonths = $this->calculateOverdueMonths($customer);

        // Hanya kirim jika tunggakan >= 4 bulan (lebih dari 3 bulan)
        if ($overdueMonths < 4) {
            return ['success' => false, 'message' => 'Overdue less than 4 months, notification skipped'];
        }

        $message = $this->buildSevereOverdueMessage($customer, $overdueMonths);

        return $this->sendWhatsApp($customer->phone, $message);
    }

    /**
     * Hitung jumlah bulan tunggakan pelanggan
     */
    protected function calculateOverdueMonths(Customer $customer): int
    {
        // Ambil invoice tertua yang belum lunas
        $oldestUnpaidInvoice = Invoice::where('customer_id', $customer->id)
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->orderBy('period_year', 'asc')
            ->orderBy('period_month', 'asc')
            ->first();

        if (!$oldestUnpaidInvoice) {
            return 0;
        }

        // Hitung selisih bulan dari invoice tertua hingga sekarang
        $invoiceDate = \Carbon\Carbon::create(
            $oldestUnpaidInvoice->period_year,
            $oldestUnpaidInvoice->period_month,
            1
        );

        return $invoiceDate->diffInMonths(now());
    }

    /**
     * Send isolation notice
     */
    public function sendIsolationNotice(Customer $customer): array
    {
        $message = $this->buildIsolationMessage($customer);

        return $this->sendWhatsApp($customer->phone, $message);
    }

    /**
     * Send access reopened notice
     */
    public function sendAccessOpenedNotice(Customer $customer): array
    {
        $message = $this->buildAccessOpenedMessage($customer);

        return $this->sendWhatsApp($customer->phone, $message);
    }

    /**
     * Send payment confirmation
     */
    public function sendPaymentConfirmation(Customer $customer, Payment $payment): array
    {
        $message = $this->buildPaymentConfirmationMessage($customer, $payment);

        return $this->sendWhatsApp($customer->phone, $message);
    }

    /**
     * Send OTP code
     */
    public function sendOTP(Customer $customer, string $otp): array
    {
        $message = $this->buildOtpMessage($otp);

        // OTP via WhatsApp only for security
        return $this->sendWhatsApp($customer->phone, $message);
    }

    // ================================================================
    // BULK NOTIFICATIONS
    // ================================================================

    /**
     * Send bulk reminders (for scheduled job)
     */
    public function sendBulkReminders(array $customerIds, int $daysBeforeDue = 3): array
    {
        $results = ['success' => 0, 'failed' => 0, 'skipped' => 0];

        $customers = Customer::whereIn('id', $customerIds)
            ->where('status', 'active')
            ->where('total_debt', '>', 0)
            ->get();

        foreach ($customers as $customer) {
            // Use queue for bulk sending
            $this->sendAsync(
                'whatsapp',
                $customer->phone,
                $this->buildReminderMessage($customer, $daysBeforeDue)
            );
            $results['success']++;
        }

        return $results;
    }

    /**
     * Send broadcast message to all active customers
     */
    public function sendBroadcast(string $message, ?array $customerIds = null): array
    {
        $query = Customer::where('status', 'active');

        if ($customerIds) {
            $query->whereIn('id', $customerIds);
        }

        $customers = $query->get();
        $sent = 0;

        foreach ($customers as $customer) {
            $this->sendAsync('whatsapp', $customer->phone, $message);
            $sent++;
        }

        return ['sent' => $sent];
    }

    // ================================================================
    // MESSAGE BUILDERS
    // ================================================================

    protected function buildInvoiceMessage(Customer $customer, Invoice $invoice): string
    {
        $companyName = $this->ispInfo?->company_name ?? 'ISP';

        return "Yth. Bapak/Ibu *{$customer->name}*,\n\n" .
            "Tagihan internet Anda untuk periode *{$invoice->period_label}* telah terbit.\n\n" .
            "📋 *Detail Tagihan:*\n" .
            "No. Invoice: {$invoice->invoice_number}\n" .
            "Paket: {$invoice->package_name}\n" .
            "Total: *Rp " . number_format($invoice->total_amount, 0, ',', '.') . "*\n" .
            "Jatuh Tempo: *" . $invoice->due_date->format('d M Y') . "*\n\n" .
            "💳 *Pembayaran:*\n" .
            $this->formatPaymentInfo() . "\n\n" .
            "Gunakan ID Pelanggan *{$customer->customer_id}* sebagai keterangan transfer.\n\n" .
            "Terima kasih.\n" .
            "_{$companyName}_";
    }

    protected function buildReminderMessage(Customer $customer, int $daysBeforeDue): string
    {
        $companyName = $this->ispInfo?->company_name ?? 'ISP';
        $totalDebt = number_format($customer->total_debt, 0, ',', '.');

        $urgency = $daysBeforeDue <= 1 ? '⚠️ *SEGERA*' : '📢 *PENGINGAT*';

        return "{$urgency}\n\n" .
            "Yth. Bapak/Ibu *{$customer->name}*,\n\n" .
            "Tagihan internet Anda sebesar *Rp {$totalDebt}* akan jatuh tempo dalam *{$daysBeforeDue} hari*.\n\n" .
            "Mohon segera lakukan pembayaran untuk menghindari pemutusan layanan.\n\n" .
            "💳 *Pembayaran:*\n" .
            $this->formatPaymentInfo() . "\n\n" .
            "ID Pelanggan: *{$customer->customer_id}*\n\n" .
            "Abaikan pesan ini jika sudah melakukan pembayaran.\n\n" .
            "Terima kasih.\n" .
            "_{$companyName}_";
    }

    protected function buildOverdueMessage(Customer $customer): string
    {
        $companyName = $this->ispInfo?->company_name ?? 'ISP';
        $totalDebt = number_format($customer->total_debt, 0, ',', '.');

        return "⚠️ *TAGIHAN JATUH TEMPO*\n\n" .
            "Yth. Bapak/Ibu *{$customer->name}*,\n\n" .
            "Tagihan internet Anda sebesar *Rp {$totalDebt}* telah melewati jatuh tempo.\n\n" .
            "Mohon *segera* lakukan pembayaran untuk menghindari isolir/pemutusan layanan.\n\n" .
            "💳 *Pembayaran:*\n" .
            $this->formatPaymentInfo() . "\n\n" .
            "ID Pelanggan: *{$customer->customer_id}*\n\n" .
            "Hubungi kami jika ada kendala pembayaran.\n" .
            "📞 " . ($this->ispInfo?->phone_primary ?? '') . "\n\n" .
            "_{$companyName}_";
    }

    protected function buildIsolationMessage(Customer $customer): string
    {
        $companyName = $this->ispInfo?->company_name ?? 'ISP';
        $totalDebt = number_format($customer->total_debt, 0, ',', '.');
        $portalUrl = config('app.url') . '/portal/isolation/' . $customer->id;

        return "🔴 *PEMBERITAHUAN ISOLIR*\n\n" .
            "Yth. Bapak/Ibu *{$customer->name}*,\n\n" .
            "Dengan berat hati kami informasikan bahwa layanan internet Anda telah *DIISOLIR* karena tunggakan pembayaran.\n\n" .
            "💰 *Total Tunggakan:* Rp {$totalDebt}\n\n" .
            "Untuk mengaktifkan kembali layanan, silakan:\n" .
            "1. Lakukan pembayaran ke:\n" .
            $this->formatPaymentInfo() . "\n" .
            "2. Kirim bukti transfer via WhatsApp\n" .
            "3. Layanan akan aktif dalam 1x24 jam\n\n" .
            "📱 Lihat detail: {$portalUrl}\n\n" .
            "Hubungi kami:\n" .
            "📞 " . ($this->ispInfo?->phone_primary ?? '') . "\n" .
            "💬 WA: " . ($this->ispInfo?->whatsapp_number ?? '') . "\n\n" .
            "_{$companyName}_";
    }

    protected function buildSevereOverdueMessage(Customer $customer, int $overdueMonths): string
    {
        $companyName = $this->ispInfo?->company_name ?? 'ISP';
        $totalDebt = number_format($customer->total_debt, 0, ',', '.');

        // Ambil detail tunggakan per bulan
        $overdueDetail = $this->getOverdueDetail($customer);

        return "📋 *PEMBERITAHUAN TUNGGAKAN*\n\n" .
            "Yth. Bapak/Ibu *{$customer->name}*,\n\n" .
            "Dengan hormat,\n" .
            "Kami ingin menyampaikan bahwa tagihan internet Anda telah menunggak selama *{$overdueMonths} bulan*.\n\n" .
            "📊 *Rincian Tunggakan:*\n" .
            $overdueDetail . "\n" .
            "💰 *Total Tunggakan:* Rp {$totalDebt}\n\n" .
            "Kami memahami bahwa setiap pelanggan memiliki kondisi yang berbeda. " .
            "Jika Bapak/Ibu mengalami kendala dalam pembayaran, kami dengan senang hati dapat membantu mencari solusi terbaik.\n\n" .
            "Silakan hubungi kami untuk konsultasi:\n" .
            "📞 " . ($this->ispInfo?->phone_primary ?? '') . "\n" .
            "💬 WA: " . ($this->ispInfo?->whatsapp_number ?? '') . "\n\n" .
            "💳 *Pembayaran dapat dilakukan ke:*\n" .
            $this->formatPaymentInfo() . "\n\n" .
            "ID Pelanggan: *{$customer->customer_id}*\n\n" .
            "Terima kasih atas perhatian dan kerjasamanya.\n\n" .
            "Hormat kami,\n" .
            "_{$companyName}_";
    }

    /**
     * Ambil detail tunggakan per bulan
     */
    protected function getOverdueDetail(Customer $customer): string
    {
        $unpaidInvoices = Invoice::where('customer_id', $customer->id)
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->orderBy('period_year', 'asc')
            ->orderBy('period_month', 'asc')
            ->get();

        if ($unpaidInvoices->isEmpty()) {
            return "- Tidak ada tunggakan\n";
        }

        $monthNames = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];

        $details = $unpaidInvoices->map(function ($invoice) use ($monthNames) {
            $monthName = $monthNames[$invoice->period_month] ?? $invoice->period_month;
            $amount = number_format($invoice->remaining_amount, 0, ',', '.');
            return "• {$monthName} {$invoice->period_year}: Rp {$amount}";
        });

        return $details->join("\n") . "\n";
    }

    protected function buildAccessOpenedMessage(Customer $customer): string
    {
        $companyName = $this->ispInfo?->company_name ?? 'ISP';

        return "✅ *LAYANAN AKTIF KEMBALI*\n\n" .
            "Yth. Bapak/Ibu *{$customer->name}*,\n\n" .
            "Pembayaran Anda telah kami terima.\n" .
            "Layanan internet Anda telah *AKTIF KEMBALI*.\n\n" .
            "Terima kasih atas kepercayaan Anda menggunakan layanan kami.\n\n" .
            "Jika ada kendala koneksi, silakan hubungi:\n" .
            "📞 " . ($this->ispInfo?->phone_primary ?? '') . "\n" .
            "💬 WA: " . ($this->ispInfo?->whatsapp_number ?? '') . "\n\n" .
            "_{$companyName}_";
    }

    protected function buildPaymentConfirmationMessage(Customer $customer, Payment $payment): string
    {
        $companyName = $this->ispInfo?->company_name ?? 'ISP';
        $amount = number_format($payment->amount, 0, ',', '.');
        $remaining = number_format($customer->total_debt, 0, ',', '.');

        return "✅ *KONFIRMASI PEMBAYARAN*\n\n" .
            "Yth. Bapak/Ibu *{$customer->name}*,\n\n" .
            "Pembayaran Anda telah kami terima:\n\n" .
            "📋 *Detail:*\n" .
            "No. Pembayaran: {$payment->payment_number}\n" .
            "Jumlah: *Rp {$amount}*\n" .
            "Metode: {$payment->method_label}\n" .
            "Tanggal: " . $payment->created_at->format('d M Y H:i') . "\n\n" .
            ($customer->total_debt > 0
                ? "Sisa tagihan: Rp {$remaining}\n\n"
                : "✨ Tagihan Anda sudah *LUNAS*\n\n") .
            "Terima kasih.\n" .
            "_{$companyName}_";
    }

    protected function buildOtpMessage(string $otp): string
    {
        $companyName = $this->ispInfo?->company_name ?? 'ISP';

        return "🔐 *KODE OTP*\n\n" .
            "Kode OTP Anda: *{$otp}*\n\n" .
            "Kode berlaku selama 5 menit.\n" .
            "Jangan berikan kode ini kepada siapapun.\n\n" .
            "_{$companyName}_";
    }

    // ================================================================
    // HELPERS
    // ================================================================

    protected function formatPaymentInfo(): string
    {
        if (!$this->ispInfo || empty($this->ispInfo->bank_accounts)) {
            return "Hubungi admin untuk info pembayaran";
        }

        $banks = collect($this->ispInfo->bank_accounts)->take(2);

        return $banks->map(function ($bank) {
            return "• {$bank['bank']}: {$bank['account']} a.n {$bank['name']}";
        })->join("\n");
    }

    protected function normalizePhone(string $phone): string
    {
        // Remove all non-digits
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Convert 08 to 628
        if (str_starts_with($phone, '0')) {
            $phone = '62' . substr($phone, 1);
        }

        // Add 62 if not present
        if (!str_starts_with($phone, '62')) {
            $phone = '62' . $phone;
        }

        return $phone;
    }

    protected function shortenMessage(string $message): string
    {
        // Remove markdown formatting for SMS
        $message = preg_replace('/\*([^*]+)\*/', '$1', $message);
        $message = preg_replace('/_([^_]+)_/', '$1', $message);

        // Truncate if too long
        if (strlen($message) > 160) {
            $message = substr($message, 0, 157) . '...';
        }

        return $message;
    }

    protected function isWhatsAppEnabled(): bool
    {
        return Setting::getValue('notification', 'whatsapp_enabled', true);
    }

    protected function isEmailEnabled(): bool
    {
        return Setting::getValue('notification', 'email_enabled', false);
    }

    protected function logNotification(string $channel, string $recipient, string $message, bool $success): void
    {
        BillingLog::logSystem(
            'notification_sent',
            "Notification via {$channel} to {$recipient}",
            [
                'channel' => $channel,
                'recipient' => $recipient,
                'message_preview' => substr($message, 0, 100),
                'success' => $success,
            ]
        );
    }
}
