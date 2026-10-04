<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ $systemName ?? fleet_system_name() }} | POS</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <link rel="stylesheet" href="{{ asset('AdminLTE-2/bower_components/bootstrap/dist/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('AdminLTE-2/bower_components/font-awesome/css/font-awesome.min.css') }}">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,600,700">
    <link rel="stylesheet" href="{{ asset('css/fleet-theme.css') }}?v=9">
    <link rel="stylesheet" href="{{ asset('css/sellix-app.css') }}?v=38">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
</head>
<body class="pos-body sellix-app">
    <header class="pos-topbar">
        <div class="pos-brand">
            @if(!empty($companyLogoUrl))
                <img src="{{ $companyLogoUrl }}" alt="" class="pos-brand-logo">
            @else
                <span class="pos-brand-mark"></span>
            @endif
            <span>{{ $systemShortName ?? $systemName ?? fleet_system_short_name() }}</span>
        </div>
        <div class="pos-top-actions">
            <button type="button" class="pos-top-link" id="sx-pos-new"><i class="fa fa-file-o"></i> New</button>
            <button type="button" class="pos-top-link" id="sx-pos-holds-top">
                Hold List
                <span class="pos-hold-badge" id="sx-pos-hold-count">{{ (int) $heldCount }}</span>
            </button>
            <a href="{{ route('dashboard') }}" class="pos-top-link" id="sx-pos-dashboard"><i class="fa fa-dashboard"></i> Dashboard</a>
            <form action="{{ route('logout') }}" method="post" class="pos-logout-form">
                @csrf
                <button type="submit" class="pos-logout">LOGOUT</button>
            </form>
            <span class="pos-user"><i class="fa fa-user-circle"></i> {{ auth()->user()->name }}</span>
        </div>
    </header>
    @if(session('success'))
        <div class="alert alert-success" style="margin:8px 16px 0;">{{ session('success') }}</div>
    @endif
    @yield('content')
    <footer class="pos-bottombar">
        <span>COPYRIGHT &copy; {{ date('Y') }} ALL RIGHTS RESERVED.</span>
        <span>{{ $systemShortName ?? $systemName ?? fleet_system_short_name() }} {{ $companyName ?? '' }}</span>
    </footer>
    <script src="{{ asset('AdminLTE-2/bower_components/jquery/dist/jquery.min.js') }}"></script>
    <script src="{{ asset('AdminLTE-2/bower_components/bootstrap/dist/js/bootstrap.min.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    @stack('scripts')
</body>
</html>
