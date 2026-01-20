<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mikrotik API Configuration
    |--------------------------------------------------------------------------
    |
    | Konfigurasi default untuk koneksi ke router Mikrotik.
    | Setiap router bisa memiliki konfigurasi sendiri di database.
    |
    */

    'default_port' => env('MIKROTIK_DEFAULT_PORT', 8728),
    'timeout' => env('MIKROTIK_TIMEOUT', 10),
    'debug' => env('MIKROTIK_DEBUG', false),
    'log_channel' => env('MIKROTIK_LOG_CHANNEL', 'daily'),

    /*
    |--------------------------------------------------------------------------
    | Isolation Configuration
    |--------------------------------------------------------------------------
    |
    | Metode isolir pelanggan yang digunakan:
    | - address_list: Menambahkan IP ke address list firewall
    | - profile: Mengubah PPP profile ke profile isolir
    | - disable: Menonaktifkan PPP secret
    |
    | Metode address_list adalah yang paling fleksibel karena:
    | - Tidak mengubah konfigurasi PPPoE
    | - Mudah dikombinasikan dengan redirect page isolir
    | - Tidak memutus koneksi secara permanen
    |
    */

    'isolation' => [
        // Metode isolir: address_list, profile, disable
        'method' => env('MIKROTIK_ISOLATION_METHOD', 'address_list'),

        // Nama address list untuk isolir (jika method = address_list)
        'address_list' => env('MIKROTIK_ISOLATION_ADDRESS_LIST', 'isolir'),

        // Nama profile untuk isolir (jika method = profile)
        'profile' => env('MIKROTIK_ISOLATION_PROFILE', 'isolir'),

        // Apakah disconnect session setelah isolir
        'disconnect_session' => env('MIKROTIK_DISCONNECT_ON_ISOLATE', true),

        // Redirect URL untuk halaman isolir (opsional)
        'redirect_url' => env('MIKROTIK_ISOLATION_REDIRECT_URL', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto Isolation Schedule
    |--------------------------------------------------------------------------
    |
    | Konfigurasi untuk proses isolir otomatis.
    |
    */

    'auto_isolation' => [
        // Aktifkan auto isolir
        'enabled' => env('MIKROTIK_AUTO_ISOLATE', true),

        // Jam eksekusi isolir harian
        'time' => env('MIKROTIK_ISOLATION_TIME', '08:00'),

        // Jumlah bulan tunggakan untuk isolir
        'threshold_months' => env('MIKROTIK_ISOLATION_THRESHOLD', 2),

        // Hari grace period setelah jatuh tempo
        'grace_period_days' => env('MIKROTIK_GRACE_PERIOD', 7),

        // Exception: jangan isolir jika baru bayar dalam X hari terakhir
        'recent_payment_days' => env('MIKROTIK_RECENT_PAYMENT_DAYS', 30),

        // Exception: jangan isolir pelanggan rapel
        'exclude_rapel' => env('MIKROTIK_EXCLUDE_RAPEL', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | PPPoE Configuration
    |--------------------------------------------------------------------------
    |
    | Konfigurasi default untuk PPPoE.
    |
    */

    'pppoe' => [
        // Service name
        'service' => env('MIKROTIK_PPPOE_SERVICE', 'pppoe'),

        // Default profile untuk pelanggan baru
        'default_profile' => env('MIKROTIK_DEFAULT_PROFILE', 'default'),

        // IP pool untuk remote address
        'ip_pool' => env('MIKROTIK_IP_POOL', 'pppoe-pool'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Konfigurasi untuk simple queue.
    |
    */

    'queue' => [
        // Apakah menggunakan simple queue (selain PPP profile)
        'enabled' => env('MIKROTIK_QUEUE_ENABLED', false),

        // Parent queue
        'parent' => env('MIKROTIK_QUEUE_PARENT', 'none'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Retry Configuration
    |--------------------------------------------------------------------------
    |
    | Konfigurasi retry untuk operasi yang gagal.
    |
    */

    'retry' => [
        'max_attempts' => 3,
        'delay_seconds' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Firewall Rules Template
    |--------------------------------------------------------------------------
    |
    | Template untuk firewall rule yang perlu dibuat di router.
    | Jalankan perintah ini di router untuk setup awal.
    |
    */

    'firewall_setup' => <<<'MIKROTIK'
# Address List untuk isolir
/ip firewall address-list
add list=isolir comment="Daftar IP terisolir"

# Filter rule untuk redirect ke halaman isolir
/ip firewall nat
add chain=dstnat src-address-list=isolir dst-port=80 protocol=tcp action=dst-nat to-addresses=<SERVER_IP> to-ports=<ISOLATION_PORT> comment="Redirect isolir ke portal"

# Atau untuk full block (tanpa redirect)
/ip firewall filter
add chain=forward src-address-list=isolir action=drop comment="Block akses internet isolir"
add chain=forward dst-address-list=isolir action=drop comment="Block akses internet isolir"
MIKROTIK,

];
