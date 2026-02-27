<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title') - {{ $companyName }}</title>
    <style>
        body { margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f6f8; color: #333; }
        .container { max-width: 600px; margin: 0 auto; background: #fff; }
        .header { background: #1a56db; padding: 24px; text-align: center; }
        .header h1 { color: #fff; margin: 0; font-size: 22px; }
        .header p { color: #c3d9ff; margin: 4px 0 0; font-size: 13px; }
        .content { padding: 32px 24px; }
        .footer { background: #f4f6f8; padding: 20px 24px; text-align: center; font-size: 12px; color: #888; }
        .footer a { color: #1a56db; text-decoration: none; }
        .btn { display: inline-block; background: #1a56db; color: #fff !important; padding: 12px 28px; border-radius: 6px; text-decoration: none; font-weight: 600; margin: 16px 0; }
        .info-box { background: #f0f7ff; border-left: 4px solid #1a56db; padding: 16px; margin: 16px 0; border-radius: 0 6px 6px 0; }
        .warning-box { background: #fef3cd; border-left: 4px solid #f59e0b; padding: 16px; margin: 16px 0; border-radius: 0 6px 6px 0; }
        .danger-box { background: #fee2e2; border-left: 4px solid #ef4444; padding: 16px; margin: 16px 0; border-radius: 0 6px 6px 0; }
        .success-box { background: #d1fae5; border-left: 4px solid #10b981; padding: 16px; margin: 16px 0; border-radius: 0 6px 6px 0; }
        table.detail { width: 100%; border-collapse: collapse; margin: 12px 0; }
        table.detail td { padding: 8px 0; border-bottom: 1px solid #eee; }
        table.detail td:first-child { color: #666; width: 40%; }
        table.detail td:last-child { font-weight: 600; text-align: right; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{ $companyName }}</h1>
            <p>@yield('header-subtitle', 'Internet Service Provider')</p>
        </div>
        <div class="content">
            @yield('content')
        </div>
        <div class="footer">
            <p>&copy; {{ date('Y') }} {{ $companyName }}. Seluruh hak dilindungi.</p>
            @if(!empty($companyPhone))
                <p>Hubungi kami: {{ $companyPhone }}</p>
            @endif
        </div>
    </div>
</body>
</html>
