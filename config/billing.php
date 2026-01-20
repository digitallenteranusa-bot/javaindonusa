<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Invoice Configuration
    |--------------------------------------------------------------------------
    */

    // Tanggal jatuh tempo default (tanggal dalam bulan)
    'due_days' => env('BILLING_DUE_DAYS', 20),

    // Grace period sebelum isolir (hari setelah jatuh tempo)
    'grace_days' => env('BILLING_GRACE_DAYS', 7),

    // Tanggal generate invoice otomatis
    'invoice_generate_day' => env('BILLING_INVOICE_DAY', 1),

    /*
    |--------------------------------------------------------------------------
    | Isolation Configuration
    |--------------------------------------------------------------------------
    */

    // Jumlah bulan tunggak minimal sebelum isolir
    'isolation_min_months' => env('BILLING_ISOLATION_MIN_MONTHS', 2),

    // Toleransi pembayaran terakhir (hari)
    // Jika ada pembayaran dalam X hari terakhir, tidak akan diisolir
    'recent_payment_tolerance_days' => env('BILLING_RECENT_PAYMENT_DAYS', 30),

    // Toleransi khusus untuk pelanggan rapel (bulan)
    'rapel_tolerance_months' => env('BILLING_RAPEL_TOLERANCE_MONTHS', 3),

    /*
    |--------------------------------------------------------------------------
    | Payment Configuration
    |--------------------------------------------------------------------------
    */

    // Batas waktu pembatalan pembayaran (jam)
    'payment_cancel_hours' => env('BILLING_PAYMENT_CANCEL_HOURS', 24),

    // Metode pembayaran yang tersedia
    'payment_methods' => [
        'cash' => 'Tunai',
        'transfer' => 'Transfer Bank',
        'qris' => 'QRIS',
        'ewallet' => 'E-Wallet',
    ],

    // Channel pembayaran
    'payment_channels' => [
        'collector' => 'Penagih',
        'office' => 'Kantor',
        'bank' => 'Bank',
        'online' => 'Online',
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Configuration
    |--------------------------------------------------------------------------
    */

    // Hari pengiriman reminder sebelum jatuh tempo
    'reminder_days_before' => env('BILLING_REMINDER_DAYS', '7,3,1'),

    // Kirim notifikasi saat invoice digenerate
    'notify_on_invoice' => env('BILLING_NOTIFY_ON_INVOICE', true),

    // Kirim notifikasi saat pembayaran diterima
    'notify_on_payment' => env('BILLING_NOTIFY_ON_PAYMENT', true),

    // Kirim notifikasi saat isolir
    'notify_on_isolation' => env('BILLING_NOTIFY_ON_ISOLATION', true),

    /*
    |--------------------------------------------------------------------------
    | Invoice Numbering
    |--------------------------------------------------------------------------
    */

    'invoice_prefix' => env('BILLING_INVOICE_PREFIX', 'INV'),
    'payment_prefix' => env('BILLING_PAYMENT_PREFIX', 'PAY'),

];
