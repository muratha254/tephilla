<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ $systemName ?? fleet_system_name() }} | @yield('title', 'Dashboard')</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">

    <link rel="stylesheet" href="{{ asset('AdminLTE-2/bower_components/bootstrap/dist/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('AdminLTE-2/bower_components/font-awesome/css/font-awesome.min.css') }}">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,600,700,300italic,400italic,600italic">
    <link rel="stylesheet" href="{{ asset('css/fleet-theme.css') }}?v=10">
    <link rel="stylesheet" href="{{ asset('css/sellix-app.css') }}?v=59">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    @if (! empty($fleetSetting))
    <style>
        :root {
            --fleet-accent: {{ $fleetSetting->admin_primary_color ?? '#3c8dbc' }};
            --fleet-sidebar: {{ $fleetSetting->sidebar_gradient_start ?? '#1a2332' }};
            --fleet-sidebar-hover: {{ $fleetSetting->sidebar_gradient_end ?? '#243044' }};
            --fleet-sidebar-active: {{ $fleetSetting->admin_secondary_color ?? '#2d3f56' }};
        }
        .fleet-sidebar {
            background: linear-gradient(180deg, {{ $fleetSetting->sidebar_gradient_start ?? '#2C3E50' }} 0%, {{ $fleetSetting->sidebar_gradient_end ?? '#1A252F' }} 100%);
            color: {{ $fleetSetting->sidebar_text_color ?? '#c8d0dc' }};
        }
        .fleet-menu > li > a,
        .fleet-submenu a {
            color: {{ $fleetSetting->sidebar_text_color ?? '#c8d0dc' }};
        }
    </style>
    @endif
    @stack('css')
</head>
<body class="fleet-app sellix-app">
    <div class="fleet-wrapper">
        @include('layouts.fleet-sidebar')

        <div class="fleet-main" id="fleet-main">
            @include('layouts.fleet-header')

            <main class="fleet-content">
                @if(session('success'))
                <div class="alert alert-success alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                    {{ session('success') }}
                </div>
                @endif
                @if(session('error'))
                <div class="alert alert-danger alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                    {{ session('error') }}
                </div>
                @endif
                @if($errors->any())
                <div class="alert alert-danger alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                    {{ $errors->first() }}
                </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>

    <script src="{{ asset('AdminLTE-2/bower_components/jquery/dist/jquery.min.js') }}"></script>
    <script src="{{ asset('AdminLTE-2/bower_components/bootstrap/dist/js/bootstrap.min.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        window.sxConfirmDelete = function (options) {
            options = options || {};
            return Swal.fire({
                title: options.title || 'Are you sure?',
                text: options.text || "You won't be able to revert this!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dd4b39',
                cancelButtonColor: '#00a65a',
                confirmButtonText: options.confirmText || 'Yes, delete it!',
                cancelButtonText: options.cancelText || 'Cancel',
                reverseButtons: true
            }).then(function (result) {
                return !!result.isConfirmed;
            });
        };

        $(document).on('submit', 'form.sx-swal-delete', function (e) {
            var form = this;
            if (form.getAttribute('data-swal-ok') === '1') {
                return true;
            }
            e.preventDefault();
            window.sxConfirmDelete({ text: form.getAttribute('data-swal-text') || undefined }).then(function (ok) {
                if (!ok) return;
                form.setAttribute('data-swal-ok', '1');
                HTMLFormElement.prototype.submit.call(form);
            });
        });

        $(document).on('click', '.sx-swal-delete[data-url]', function (e) {
            e.preventDefault();
            var trigger = this;
            var form = document.getElementById(trigger.getAttribute('data-form') || '');
            if (!form) return;
            window.sxConfirmDelete({ text: trigger.getAttribute('data-swal-text') || undefined }).then(function (ok) {
                if (!ok) return;
                form.action = trigger.getAttribute('data-url');
                HTMLFormElement.prototype.submit.call(form);
            });
        });
    </script>
    <script>
        (function () {
            var toggle = document.getElementById('fleet-sidebar-toggle');
            var sidebar = document.getElementById('fleet-sidebar');
            var main = document.getElementById('fleet-main');

            if (toggle && sidebar && main) {
                toggle.addEventListener('click', function () {
                    if (window.innerWidth <= 768) {
                        sidebar.classList.toggle('open');
                        return;
                    }
                    sidebar.classList.toggle('collapsed');
                    main.classList.toggle('expanded');
                });
            }

            document.querySelectorAll('.fleet-submenu-toggle').forEach(function (link) {
                link.addEventListener('click', function (e) {
                    var href = this.getAttribute('href') || '#';
                    if (href === '#') {
                        e.preventDefault();
                        var item = this.closest('.has-submenu');
                        if (item) {
                            item.classList.toggle('open');
                            if (item.classList.contains('open')) {
                                item.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                            }
                        }
                    }
                });
            });
        })();
    </script>
    @stack('scripts')
</body>
</html>
