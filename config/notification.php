<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Notification Channels
    |--------------------------------------------------------------------------
    |
    | Konfigurasi channel notifikasi yang digunakan.
    | Channels: whatsapp, sms, email
    |
    */

    'default_channel' => env('NOTIFICATION_DEFAULT_CHANNEL', 'whatsapp'),

    /*
    |--------------------------------------------------------------------------
    | WhatsApp Configuration
    |--------------------------------------------------------------------------
    |
    | Konfigurasi untuk WhatsApp Gateway.
    | Supported drivers: fonnte, wablas, dripsender, mekari, manual
    |
    | Fonnte: https://fonnte.com (Recommended for Indonesia)
    | Wablas: https://wablas.com
    | Dripsender: https://dripsender.id
    | Mekari Qontak: https://qontak.com (WhatsApp Business API resmi)
    | Manual: Generate wa.me links only (no automatic sending)
    |
    */

    'whatsapp' => [
        'driver' => env('WHATSAPP_DRIVER', 'fonnte'),
        'api_key' => env('WHATSAPP_API_KEY', ''),
        'sender' => env('WHATSAPP_SENDER', ''),

        // Fonnte specific
        'fonnte' => [
            'device_id' => env('FONNTE_DEVICE_ID', ''),
        ],

        // Wablas specific
        'wablas' => [
            'domain' => env('WABLAS_DOMAIN', 'pati'),
        ],

        // Mekari Qontak specific (WhatsApp Business API)
        // API Token: generate dari app.qontak.com → Settings → API token → Omnichannel → Generate
        'mekari' => [
            'api_token' => env('MEKARI_API_TOKEN', ''), // Digunakan sebagai whatsapp_api_key
            'channel_id' => env('MEKARI_CHANNEL_INTEGRATION_ID', ''),
            'template_id' => env('MEKARI_TEMPLATE_ID', ''),
        ],

        // Rate limiting
        'rate_limit' => [
            'per_minute' => env('WHATSAPP_RATE_LIMIT', 30),
            'delay_ms' => env('WHATSAPP_DELAY_MS', 100),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | SMS Configuration
    |--------------------------------------------------------------------------
    |
    | Konfigurasi untuk SMS Gateway.
    | Supported drivers: zenziva, twilio, nexmo, raja_sms, nusasms
    |
    | Zenziva: https://zenziva.net (Recommended for Indonesia)
    | Twilio: https://twilio.com
    | Nexmo/Vonage: https://vonage.com
    |
    */

    'sms' => [
        'driver' => env('SMS_DRIVER', 'zenziva'),
        'api_key' => env('SMS_API_KEY', ''),
        'username' => env('SMS_USERNAME', ''),
        'password' => env('SMS_PASSWORD', ''),
        'sender' => env('SMS_SENDER', ''),

        // Zenziva specific
        'zenziva_type' => env('ZENZIVA_TYPE', 'reguler'), // reguler or masking
    ],

    /*
    |--------------------------------------------------------------------------
    | Email Configuration
    |--------------------------------------------------------------------------
    |
    | Konfigurasi email menggunakan Laravel Mail.
    | Pastikan MAIL_* sudah dikonfigurasi di .env
    |
    */

    'email' => [
        'from_address' => env('MAIL_FROM_ADDRESS', 'noreply@example.com'),
        'from_name' => env('MAIL_FROM_NAME', 'ISP Billing'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Templates
    |--------------------------------------------------------------------------
    |
    | Template pesan notifikasi. Gunakan {variable} untuk placeholder.
    |
    */

    'templates' => [
        'invoice' => [
            'subject' => 'Tagihan Internet {period}',
            'enabled' => true,
        ],
        'reminder' => [
            'days_before' => [7, 3, 1], // Kirim reminder H-7, H-3, H-1
            'enabled' => true,
        ],
        'overdue' => [
            'days_after' => [1, 3, 7], // Kirim setelah jatuh tempo
            'enabled' => true,
        ],
        'isolation' => [
            'enabled' => true,
            'include_portal_link' => true,
        ],
        'payment_confirmation' => [
            'enabled' => true,
        ],
        'access_opened' => [
            'enabled' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Konfigurasi queue untuk pengiriman notifikasi.
    |
    */

    'queue' => [
        'enabled' => env('NOTIFICATION_QUEUE_ENABLED', true),
        'connection' => env('NOTIFICATION_QUEUE_CONNECTION', 'database'),
        'queue_name' => env('NOTIFICATION_QUEUE_NAME', 'notifications'),
        'retry_after' => 60, // seconds
        'max_attempts' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Konfigurasi logging untuk notifikasi.
    |
    */

    'logging' => [
        'enabled' => env('NOTIFICATION_LOG_ENABLED', true),
        'channel' => env('NOTIFICATION_LOG_CHANNEL', 'daily'),
        'log_content' => env('NOTIFICATION_LOG_CONTENT', false), // Log message content
    ],

    /*
    |--------------------------------------------------------------------------
    | Business Hours
    |--------------------------------------------------------------------------
    |
    | Notifikasi hanya dikirim pada jam kerja.
    | Set enabled = false untuk kirim kapan saja.
    |
    */

    'business_hours' => [
        'enabled' => env('NOTIFICATION_BUSINESS_HOURS', true),
        'start' => '08:00',
        'end' => '20:00',
        'timezone' => 'Asia/Jakarta',
        'skip_weekends' => false,
    ],

];
