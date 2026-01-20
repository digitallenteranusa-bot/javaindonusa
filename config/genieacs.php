<?php

return [

    /*
    |--------------------------------------------------------------------------
    | GenieACS NBI API Configuration
    |--------------------------------------------------------------------------
    |
    | GenieACS adalah ACS server open-source untuk TR-069.
    | NBI (Northbound Interface) adalah REST API untuk mengakses GenieACS.
    | Default port: 7557
    |
    */

    'enabled' => env('GENIEACS_ENABLED', false),

    'nbi_url' => env('GENIEACS_NBI_URL', 'http://localhost:7557'),
    'fs_url' => env('GENIEACS_FS_URL', 'http://localhost:7567'),
    'timeout' => env('GENIEACS_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Authentication (if enabled on GenieACS)
    |--------------------------------------------------------------------------
    */

    'auth' => [
        'enabled' => env('GENIEACS_AUTH_ENABLED', false),
        'username' => env('GENIEACS_USERNAME', ''),
        'password' => env('GENIEACS_PASSWORD', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Sync Configuration
    |--------------------------------------------------------------------------
    */

    'sync' => [
        'interval' => env('GENIEACS_SYNC_INTERVAL', 15), // minutes
        'batch_size' => env('GENIEACS_SYNC_BATCH', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | TR-069 Parameters to Sync
    |--------------------------------------------------------------------------
    |
    | Parameter paths yang akan di-sync dari device.
    | Menggunakan format TR-069 standard.
    |
    */

    'sync_parameters' => [
        // Device Info
        'serial_number' => 'DeviceID.SerialNumber',
        'manufacturer' => 'DeviceID.Manufacturer',
        'model' => 'DeviceID.ProductClass',
        'firmware_version' => 'Device.DeviceInfo.SoftwareVersion',
        'hardware_version' => 'Device.DeviceInfo.HardwareVersion',
        'uptime' => 'Device.DeviceInfo.UpTime',

        // WAN Info
        'wan_ip' => 'Device.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.ExternalIPAddress',
        'wan_mac' => 'Device.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.MACAddress',

        // PON Info (untuk ONU/ONT)
        'pon_serial' => 'Device.DeviceInfo.X_VENDOR_ONT_SerialNumber',
        'rx_power' => 'Device.Optical.Interface.1.RXPower',
        'tx_power' => 'Device.Optical.Interface.1.TXPower',

        // WiFi Info
        'wifi_ssid' => 'Device.WiFi.SSID.1.SSID',
        'wifi_enabled' => 'Device.WiFi.Radio.1.Enable',
    ],

    /*
    |--------------------------------------------------------------------------
    | Task Presets
    |--------------------------------------------------------------------------
    |
    | Preset tasks yang bisa dijalankan ke device.
    |
    */

    'presets' => [
        'reboot' => [
            'name' => 'Reboot Device',
            'task' => 'reboot',
        ],
        'factory_reset' => [
            'name' => 'Factory Reset',
            'task' => 'factoryReset',
        ],
        'refresh' => [
            'name' => 'Refresh Parameters',
            'task' => 'refreshObject',
            'object' => 'Device.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Device Status Thresholds
    |--------------------------------------------------------------------------
    */

    'thresholds' => [
        // Device dianggap offline jika last inform > X menit
        'offline_minutes' => env('GENIEACS_OFFLINE_THRESHOLD', 30),

        // RX Power threshold (dBm) - normal range untuk GPON
        'rx_power_min' => -28, // Warning jika di bawah ini
        'rx_power_max' => -8,  // Warning jika di atas ini
    ],

];
