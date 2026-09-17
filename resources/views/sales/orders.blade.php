<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ $systemName ?? fleet_system_name() }} | Order Screen</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <link rel="stylesheet" href="{{ asset('AdminLTE-2/bower_components/bootstrap/dist/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('AdminLTE-2/bower_components/font-awesome/css/font-awesome.min.css') }}">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,600,700">
    <link rel="stylesheet" href="{{ asset('css/fleet-theme.css') }}?v=9">
    <link rel="stylesheet" href="{{ asset('css/sellix-app.css') }}?v=42">
</head>
<body class="pos-body sellix-app sx-order-screen">
    <header class="pos-topbar sx-order-topbar">
        <div class="sx-order-top-left">
            <a href="{{ route('dashboard') }}" class="pos-brand">
                @if(!empty($companyLogoUrl))
                    <img src="{{ $companyLogoUrl }}" alt="" class="pos-brand-logo">
                @else
                    <span class="pos-brand-mark"></span>
                @endif
                <span>{{ $systemShortName ?? $systemName ?? fleet_system_short_name() }}</span>
            </a>
            <a href="{{ route('sales.index') }}" class="pos-top-link"><i class="fa fa-list"></i> Sales List</a>
            <a href="{{ route('pos.index') }}" class="pos-top-link"><i class="fa fa-file-text-o"></i> New Invoice</a>
            <a href="{{ route('products.index') }}" class="pos-top-link"><i class="fa fa-cube"></i> Stock List</a>
        </div>
        <div class="pos-top-actions">
            <a href="{{ route('dashboard') }}" class="pos-top-link"><i class="fa fa-dashboard"></i> Dashboard</a>
            <form action="{{ route('logout') }}" method="post" class="pos-logout-form">
                @csrf
                <button type="submit" class="pos-logout">LOGOUT</button>
            </form>
            <span class="pos-user"><i class="fa fa-user-circle"></i> {{ auth()->user()->name }}</span>
        </div>
    </header>

    <div class="sx-order-page">
        <div class="sx-order-heading">
            <h1>
                <a href="{{ route('sales.index') }}" class="sx-order-back" title="Back"><i class="fa fa-arrow-circle-left"></i></a>
                PENDING ORDERS
            </h1>
            <button type="button" class="btn sx-order-refresh" id="sx-order-refresh">
                <i class="fa fa-refresh"></i> Refresh New Orders
            </button>
        </div>
        <div id="sx-order-empty" class="sx-order-empty" @if(count($orders)) style="display:none" @endif>
            Sorry!! No Order Found.!
        </div>
        <div id="sx-order-grid" class="sx-order-grid">
            @foreach($orders as $order)
                @include('sales.partials.order-card', ['order' => $order])
            @endforeach
        </div>
    </div>

    <script src="{{ asset('AdminLTE-2/bower_components/jquery/dist/jquery.min.js') }}"></script>
    <script>
    (function ($) {
        var dataUrl = @json(route('sales.orders'));

        function cardHtml(order) {
            var items = '';
            (order.items || []).forEach(function (item) {
                items += '<li><span>' + $('<div>').text(item.name || '-').html() + '</span><strong>' + (item.qty || '') + '</strong></li>';
            });
            return '<article class="sx-order-card">' +
                '<div class="sx-order-card-head">' +
                    '<strong>' + $('<div>').text(order.number || '').html() + '</strong>' +
                    '<span>' + (order.held_at || '') + '</span>' +
                '</div>' +
                '<div class="sx-order-card-meta">Customer: ' + $('<div>').text(order.customer || 'WALK-IN').html() + '</div>' +
                '<ul class="sx-order-card-items">' + items + '</ul>' +
                '<div class="sx-order-card-foot">' +
                    '<span>Ksh ' + (order.total || '0.00') + '</span>' +
                    '<a href="' + (order.resume_url || '#') + '">Open in POS</a>' +
                '</div>' +
            '</article>';
        }

        function render(orders) {
            if (!orders || !orders.length) {
                $('#sx-order-empty').show();
                $('#sx-order-grid').empty();
                return;
            }
            $('#sx-order-empty').hide();
            var html = '';
            orders.forEach(function (order) { html += cardHtml(order); });
            $('#sx-order-grid').html(html);
        }

        function refresh() {
            $.ajax({
                url: dataUrl,
                headers: { 'Accept': 'application/json' }
            }).done(function (data) {
                render(data.orders || []);
            });
        }

        $('#sx-order-refresh').on('click', refresh);
        setInterval(refresh, 20000);
    })(jQuery);
    </script>
</body>
</html>
