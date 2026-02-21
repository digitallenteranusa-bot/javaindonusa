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
        if ($customer->total_debt <= 0) {
            return ['success' => false, 'message' => 'Customer has no debt, notification skipped'];
        }

        $currentMonth = now()->month;
        $currentYear  = now()->year;
        if ($invoice->period_month == $currentMonth && $invoice->period_year == $currentYear) {
            return ['success' => false, 'message' => 'Current month invoice, notification skipped'];
        }

        $message = $this->buildInvoiceMessage($customer, $invoice);

        return $this->sendWhatsApp($customer->phone, $message, [
            'template_id' => $this->getMekariTemplateId('invoice'),
            'params'      => [
                $customer->name,                                        // {{1}}
                $invoice->period_label,                                 // {{2}}
                $invoice->invoice_number,                               // {{3}}
                $invoice->package_name,                                 // {{4}}
                number_format($invoice->total_amount, 0, ',', '.'),    // {{5}}
                $invoice->due_date->format('d M Y'),                   // {{6}}
                $customer->customer_id,                                 // {{7}}
            ],
        ]);
    }

    /**
     * Send payment reminder
     */
    public function sendPaymentReminder(Customer $customer, int $daysBeforeDue = 3): array
    {
        if ($customer->total_debt <= 0) {
            return ['success' => false, 'message' => 'Customer has no debt, notification skipped'];
        }

        $message = $this->buildReminderMessage($customer, $daysBeforeDue);

        return $this->sendWhatsApp($customer->phone, $message, [
            'template_id' => $this->getMekariTemplateId('reminder'),
            'params'      => [
                $customer->name,                                        // {{1}}
                number_format($customer->total_debt, 0, ',', '.'),     // {{2}}
                $daysBeforeDue,                                         // {{3}}
                $customer->customer_id,                                 // {{4}}
            ],
        ]);
    }

    /**
     * Send overdue notice
     */
    public function sendOverdueNotice(Customer $customer): array
    {
        if ($customer->total_debt <= 0) {
            return ['success' => false, 'message' => 'Customer has no debt, notification skipped'];
        }

        $message = $this->buildOverdueMessage($customer);

        return $this->sendWhatsApp($customer->phone, $message, [
            'template_id' => $this->getMekariTemplateId('overdue'),
            'params'      => [
                $customer->name,                                        // {{1}}
                number_format($customer->total_debt, 0, ',', '.'),     // {{2}}
                $customer->customer_id,                                 // {{3}}
            ],
        ]);
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

        return $this->sendWhatsApp($customer->phone, $message, [
            'template_id' => $this->getMekariTemplateId('isolation'),
            'params'      => [
                $customer->name,                                        // {{1}}
                number_format($customer->total_debt, 0, ',', '.'),     // {{2}}
                $this->ispInfo?->phone_primary ?? '',                   // {{3}}
                $customer->customer_id,                                 // {{4}}
            ],
        ]);
    }

    /**
     * Send access reopened notice
     */
    public function sendAccessOpenedNotice(Customer $customer): array
    {
        $message = $this->buildAccessOpenedMessage($customer);

        return $this->sendWhatsApp($customer->phone, $message, [
            'template_id' => $this->getMekariTemplateId('access_opened'),
            'params'      => [
                $customer->name,                                        // {{1}}
                $this->ispInfo?->phone_primary ?? '',                   // {{2}}
            ],
        ]);
    }

    /**
     * Send payment confirmation
     */
    public function sendPaymentConfirmation(Customer $customer, Payment $payment): array
    {
        $message = $this->buildPaymentConfirmationMessage($customer, $payment);
        $amount    = number_format($payment->amount, 0, ',', '.');
        $remaining = number_format($customer->total_debt, 0, ',', '.');
        $status    = $customer->total_debt > 0 ? 'Sisa Rp ' . $remaining : 'LUNAS';

        return $this->sendWhatsApp($customer->phone, $message, [
            'template_id' => $this->getMekariTemplateId('payment'),
            'params'      => [
                $customer->name,                              // {{1}}
                $amount,                                      // {{2}}
                $payment->payment_number,                     // {{3}}
                $payment->created_at->format('d M Y H:i'),   // {{4}}
                $status,                                      // {{5}}
            ],
        ]);
    }

    /**
     * Send OTP code
     */
    public function sendOTP(Customer $customer, string $otp): array
    {
        $message = $this->buildOtpMessage($otp);

        return $this->sendWhatsApp($customer->phone, $message, [
            'template_id' => $this->getMekariTemplateId('otp'),
            'params'      => [
                $otp, // {{1}}
            ],
        ]);
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

        $bulkDelay = config('notification.whatsapp.rate_limit.bulk_delay_seconds', 15);

        foreach ($customers as $index => $customer) {
            $delay = $index * $bulkDelay;
            SendNotificationJob::dispatch(
                'whatsapp',
                $customer->phone,
                $this->buildReminderMessage($customer, $daysBeforeDue)
            )->delay(now()->addSeconds($delay));
            $results['success']++;
        }

        return $results;
    }

    /**
     * Send maintenance/gangguan notice to customers
     */
    public function sendMaintenanceNotice(
        string $startTime,
        string $endTime,
        string $description,
        ?array $customerIds = null
    ): array {
        $query = Customer::where('status', 'active');

        if ($customerIds) {
            $query->whereIn('id', $customerIds);
        }

        $customers = $query->get();
        $sent = 0;
        $bulkDelay = config('notification.whatsapp.rate_limit.bulk_delay_seconds', 15);

        foreach ($customers as $index => $customer) {
            $message = $this->buildMaintenanceMessage($customer, $startTime, $endTime, $description);
            $delay = $index * $bulkDelay;
            SendNotificationJob::dispatch('whatsapp', $customer->phone, $message)
                ->delay(now()->addSeconds($delay));
            $sent++;
        }

        return ['sent' => $sent];
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
        $bulkDelay = config('notification.whatsapp.rate_limit.bulk_delay_seconds', 15);

        foreach ($customers as $index => $customer) {
            $delay = $index * $bulkDelay;
            SendNotificationJob::dispatch('whatsapp', $customer->phone, $message)
                ->delay(now()->addSeconds($delay));
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

        // Hitung tanggal jatuh tempo
        $dueDate = now()->addDays($daysBeforeDue)->translatedFormat('d F Y');

        // Cek apakah ada template custom dari settings
        $customTemplate = Setting::getValue('notification', 'reminder_template', '');

        if (!empty($customTemplate)) {
            // Gunakan template custom
            $message = str_replace(
                ['{nama}', '{nominal}', '{jatuh_tempo}', '{customer_id}', '{paket}', '{hari}'],
                [
                    $customer->name,
                    'Rp ' . $totalDebt,
                    $dueDate,
                    $customer->customer_id,
                    $customer->package?->name ?? '-',
                    $daysBeforeDue,
                ],
                $customTemplate
            );

            return $message . "\n\n_{$companyName}_";
        }

        // Template default jika tidak ada custom template
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

        // Cek apakah ada template custom dari settings
        $customTemplate = Setting::getValue('notification', 'overdue_template', '');

        if (!empty($customTemplate)) {
            // Gunakan template custom
            $message = str_replace(
                ['{nama}', '{nominal}', '{customer_id}', '{paket}', '{telepon}', '{whatsapp}'],
                [
                    $customer->name,
                    'Rp ' . $totalDebt,
                    $customer->customer_id,
                    $customer->package?->name ?? '-',
                    $this->ispInfo?->phone_primary ?? '',
                    $this->ispInfo?->whatsapp_number ?? '',
                ],
                $customTemplate
            );

            return $message . "\n\n_{$companyName}_";
        }

        // Template default
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

        // Cek apakah ada template custom dari settings
        $customTemplate = Setting::getValue('notification', 'isolation_template', '');

        if (!empty($customTemplate)) {
            // Gunakan template custom
            $message = str_replace(
                ['{nama}', '{nominal}', '{customer_id}', '{paket}', '{telepon}', '{whatsapp}', '{portal_url}'],
                [
                    $customer->name,
                    'Rp ' . $totalDebt,
                    $customer->customer_id,
                    $customer->package?->name ?? '-',
                    $this->ispInfo?->phone_primary ?? '',
                    $this->ispInfo?->whatsapp_number ?? '',
                    $portalUrl,
                ],
                $customTemplate
            );

            return $message . "\n\n_{$companyName}_";
        }

        // Template default
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
        $statusText = $customer->total_debt > 0 ? "Sisa tagihan: Rp {$remaining}" : 'LUNAS';

        // Cek apakah ada template custom dari settings
        $customTemplate = Setting::getValue('notification', 'payment_confirmation_template', '');

        if (!empty($customTemplate)) {
            $message = str_replace(
                ['{nama}', '{nominal}', '{no_pembayaran}', '{metode}', '{tanggal}', '{sisa_tagihan}', '{customer_id}', '{paket}', '{status_lunas}'],
                [
                    $customer->name,
                    'Rp ' . $amount,
                    $payment->payment_number,
                    $payment->method_label,
                    $payment->created_at->format('d M Y H:i'),
                    'Rp ' . $remaining,
                    $customer->customer_id,
                    $customer->package?->name ?? '-',
                    $statusText,
                ],
                $customTemplate
            );

            return $message . "\n\n_{$companyName}_";
        }

        // Template default
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

    protected function buildMaintenanceMessage(Customer $customer, string $startTime, string $endTime, string $description): string
    {
        $companyName = $this->ispInfo?->company_name ?? 'ISP';

        // Cek apakah ada template custom dari settings
        $customTemplate = Setting::getValue('notification', 'maintenance_template', '');

        if (!empty($customTemplate)) {
            $message = str_replace(
                ['{nama}', '{tanggal_mulai}', '{tanggal_selesai}', '{keterangan}', '{telepon}', '{whatsapp}'],
                [
                    $customer->name,
                    $startTime,
                    $endTime,
                    $description,
                    $this->ispInfo?->phone_primary ?? '',
                    $this->ispInfo?->whatsapp_number ?? '',
                ],
                $customTemplate
            );

            return $message . "\n\n_{$companyName}_";
        }

        // Template default
        return "🔧 *PEMBERITAHUAN MAINTENANCE*\n\n" .
            "Yth. Bapak/Ibu *{$customer->name}*,\n\n" .
            "Kami informasikan bahwa akan dilakukan maintenance/perbaikan pada jaringan kami.\n\n" .
            "📅 *Waktu Mulai:* {$startTime}\n" .
            "📅 *Estimasi Selesai:* {$endTime}\n\n" .
            "📝 *Keterangan:*\n{$description}\n\n" .
            "Selama proses ini, layanan internet Anda mungkin mengalami gangguan sementara. " .
            "Kami mohon maaf atas ketidaknyamanannya.\n\n" .
            "Hubungi kami jika ada pertanyaan:\n" .
            "📞 " . ($this->ispInfo?->phone_primary ?? '') . "\n" .
            "💬 WA: " . ($this->ispInfo?->whatsapp_number ?? '') . "\n\n" .
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

    /**
     * Ambil Mekari Template ID per jenis notifikasi
     * Fallback ke template ID default jika tidak dikonfigurasi
     */
    protected function getMekariTemplateId(string $type): string
    {
        return Setting::getValue('notification', 'whatsapp_mekari_tpl_' . $type, '')
            ?: Setting::getValue('notification', 'whatsapp_mekari_template_id', '');
    }

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
