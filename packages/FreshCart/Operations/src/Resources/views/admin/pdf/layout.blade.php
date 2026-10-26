<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $title }}</title>

    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; margin: 24px; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        .muted { color: #555; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #ccc; padding: 5px 6px; text-align: left; vertical-align: top; }
        th { background: #f2f2f2; }
        .right { text-align: right; }
        .grid { width: 100%; margin-top: 12px; }
        .grid td { border: none; padding: 2px 0; width: 50%; }
        .signatures td { border: none; padding-top: 36px; }
        .line { border-top: 1px solid #333; padding-top: 4px; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <p class="muted">{{ core()->getCurrentChannel()?->name ?? config('app.name') }} · @lang('operations::app.pdf.generated', ['date' => now()->format('d M Y H:i')])</p>

    @yield('content')
</body>
</html>
