<?php

return [
    'enabled' => env('TRIPAY_ENABLED', false),
    'sandbox' => env('TRIPAY_SANDBOX', true),
    'api_key' => env('TRIPAY_API_KEY', ''),
    'private_key' => env('TRIPAY_PRIVATE_KEY', ''),
    'merchant_code' => env('TRIPAY_MERCHANT_CODE', ''),
    'base_url' => env('TRIPAY_SANDBOX', true)
        ? 'https://tripay.co.id/api-sandbox'
        : 'https://tripay.co.id/api',
    'callback_url' => env('TRIPAY_CALLBACK_URL', '/api/tripay/callback'),
    'return_url' => env('TRIPAY_RETURN_URL', '/portal'),
];
