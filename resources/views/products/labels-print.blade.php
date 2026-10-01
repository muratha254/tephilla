<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $systemName ?? 'TEPHILLA SYSTEM' }} | Print Labels</title>
    <link rel="stylesheet" href="{{ asset('css/sellix-app.css') }}?v=15">
    <style>
        body { font-family: Arial, sans-serif; margin: 16px; background: #fff; }
        .no-print { margin-bottom: 12px; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <p class="no-print">
        <button onclick="window.print()">Print</button>
        <button onclick="window.close()">Close</button>
    </p>
    @include('products.partials.label-stickers')
    @if(!empty($autoPrint))
    <script>window.addEventListener('load', function () { window.print(); });</script>
    @endif
</body>
</html>
