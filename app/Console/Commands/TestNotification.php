<?php

namespace App\Console\Commands;

use App\Services\Notification\NotificationService;
use App\Services\Notification\Channels\WhatsAppChannel;
use App\Services\Notification\Channels\SmsChannel;
use Illuminate\Console\Command;

class TestNotification extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'notification:test
                            {channel : Channel to test (whatsapp, sms, email)}
                            {recipient : Phone number or email address}
                            {--message= : Custom test message}';

    /**
     * The console command description.
     */
    protected $description = 'Test notification channels';

    /**
     * Execute the console command.
     */
    public function handle(NotificationService $notificationService): int
    {
        $channel = $this->argument('channel');
        $recipient = $this->argument('recipient');
        $message = $this->option('message') ?? $this->getDefaultMessage($channel);

        $this->info("Testing {$channel} notification...");
        $this->newLine();

        $this->table(['Setting', 'Value'], [
            ['Channel', $channel],
            ['Recipient', $recipient],
            ['Message Length', strlen($message) . ' chars'],
        ]);

        $this->newLine();

        // Check connection first
        if ($channel === 'whatsapp') {
            $this->info('Checking WhatsApp API connection...');
            $whatsapp = app(WhatsAppChannel::class);
            $status = $whatsapp->checkStatus();

            if ($status['success']) {
                $this->info('  ✓ API connection OK');
            } else {
                $this->error('  ✗ API connection failed: ' . ($status['status'] ?? 'Unknown error'));

                if (!$this->confirm('Continue anyway?')) {
                    return Command::FAILURE;
                }
            }
        }

        if ($channel === 'sms') {
            $this->info('Checking SMS API connection...');
            $sms = app(SmsChannel::class);
            $balance = $sms->checkBalance();

            if ($balance['success']) {
                $this->info('  ✓ API connection OK (Balance: ' . ($balance['balance'] ?? 'N/A') . ')');
            } else {
                $this->warn('  ! Could not check balance: ' . ($balance['message'] ?? 'Unknown'));
            }
        }

        $this->newLine();
        $this->info('Sending test message...');

        $result = match ($channel) {
            'whatsapp' => $notificationService->sendWhatsApp($recipient, $message),
            'sms' => $notificationService->sendSms($recipient, $message),
            'email' => $notificationService->sendEmail($recipient, 'Test Notification', $message),
            default => ['success' => false, 'message' => 'Unknown channel'],
        };

        $this->newLine();

        if ($result['success']) {
            $this->info('✓ Message sent successfully!');

            if (!empty($result['message_id'])) {
                $this->info("  Message ID: {$result['message_id']}");
            }

            if (!empty($result['url'])) {
                $this->info("  WhatsApp URL: {$result['url']}");
            }

            return Command::SUCCESS;
        } else {
            $this->error('✗ Failed to send message');
            $this->error("  Error: " . ($result['message'] ?? 'Unknown error'));

            if (!empty($result['response'])) {
                $this->newLine();
                $this->warn('API Response:');
                $this->line(json_encode($result['response'], JSON_PRETTY_PRINT));
            }

            return Command::FAILURE;
        }
    }

    protected function getDefaultMessage(string $channel): string
    {
        $timestamp = now()->format('d M Y H:i:s');

        return match ($channel) {
            'whatsapp' => "🔔 *Test Notification*\n\n" .
                "Ini adalah pesan test dari sistem billing Java Indonusa.\n\n" .
                "Timestamp: {$timestamp}\n" .
                "Channel: WhatsApp\n\n" .
                "_Abaikan pesan ini_",
            'sms' => "[TEST] Pesan test dari Java Indonusa Billing. Timestamp: {$timestamp}",
            'email' => "This is a test email from Java Indonusa Billing System.\n\n" .
                "Timestamp: {$timestamp}\n\n" .
                "If you received this email, the email notification system is working correctly.",
            default => "Test notification - {$timestamp}",
        };
    }
}
