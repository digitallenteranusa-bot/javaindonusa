<?php

return [
    'enabled' => env('XENDIT_ENABLED', false),
    'secret_key' => env('XENDIT_SECRET_KEY', ''),
    'webhook_token' => env('XENDIT_WEBHOOK_TOKEN', ''),
    'base_url' => 'https://api.xendit.co',
];
