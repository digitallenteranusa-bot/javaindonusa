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

    // Metode isolasi RADIUS: pool (recommended) | rate_limit | group | delete
    'isolation_method' => env('RADIUS_ISOLATION_METHOD', 'pool'),

    // Pool isolasi — customer dapat IP dari pool ini + masuk address list ISOLIR
    // Mikrotik harus punya: /ip pool "pool-isolir" + NAT redirect ke halaman isolir
    'isolation_pool' => env('RADIUS_ISOLATION_POOL', 'pool-isolir'),

    // Address list untuk NAT redirect halaman isolir
    'isolation_address_list' => env('RADIUS_ISOLATION_ADDRESS_LIST', 'ISOLIR'),

    // Rate limit saat isolasi (hanya jika isolation_method = rate_limit)
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
