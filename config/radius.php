<?php

return [

    /*
    |--------------------------------------------------------------------------
    | RADIUS Integration
    |--------------------------------------------------------------------------
    | Konfigurasi integrasi FreeRADIUS. Set RADIUS_ENABLED=true untuk
    | mengaktifkan dual sync (Mikrotik + RADIUS).
    */

    'enabled' => env('RADIUS_ENABLED', false),

    'connection' => 'radius',

    'default_group' => 'default',

    // Metode isolasi RADIUS: rate_limit | group | delete
    'isolation_method' => env('RADIUS_ISOLATION_METHOD', 'rate_limit'),

    // Rate limit saat isolasi (format Mikrotik: upload/download)
    'isolation_rate_limit' => env('RADIUS_ISOLATION_RATE_LIMIT', '1k/1k'),

    // Group name saat isolasi (jika isolation_method = group)
    'isolation_group' => 'isolated',

    // Auto sync NAS dari tabel routers yang punya radius_server_id
    'auto_sync_nas' => true,

    // Attribute names (Mikrotik vendor-specific)
    'attributes' => [
        'rate_limit' => 'Mikrotik-Rate-Limit',
        'address_list' => 'Mikrotik-Address-List',
    ],

];
