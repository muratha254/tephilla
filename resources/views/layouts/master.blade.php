<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ $setting->nama_perusahaan }} | @yield('title')</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <!-- Tell the browser to be responsive to screen width -->
    <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">

    <link rel="icon" href="{{ url($setting->path_logo) }}" type="image/png">

    <!-- Bootstrap 3.3.7 -->
    <link rel="stylesheet" href="{{ asset('/AdminLTE-2/bower_components/bootstrap/dist/css/bootstrap.min.css') }}">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="{{ asset('/AdminLTE-2/bower_components/font-awesome/css/font-awesome.min.css') }}">
    <!-- Theme style -->
    <link rel="stylesheet" href="{{ asset('/AdminLTE-2/dist/css/AdminLTE.min.css') }}">
    <!-- AdminLTE Skins. Choose a skin from the css/skins
       folder instead of downloading all of them to reduce the load. -->
    <link rel="stylesheet" href="{{ asset('/AdminLTE-2/dist/css/skins/_all-skins.min.css') }}">
    <!-- DataTables -->
    <link rel="stylesheet" href="{{ asset('/AdminLTE-2/bower_components/datatables.net-bs/css/dataTables.bootstrap.min.css') }}">
    <!-- Synced top horizontal scrollbar for wide tables (see table-responsive-hscroll.js) -->
    <link rel="stylesheet" href="{{ asset('css/table-responsive-hscroll.css') }}">

    <!-- HTML5 Shim and Respond.js IE8 support of HTML5 elements and media queries -->
    <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
    <!--[if lt IE 9]>
  <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
  <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
  <![endif]-->

    <!-- Google Font -->
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,600,700,300italic,400italic,600italic">

    @stack('css')
    <!-- After AdminLTE skins: sidebar category (treeview) highlights -->
    <link rel="stylesheet" href="{{ asset('css/sidebar-treeview-highlight.css') }}?v=5">
    
</head>
<!-- visit "codeastro" for more projects! -->
<body class="hold-transition skin-green sidebar-mini">
    <div id="chat-notification-container" style="position:fixed;top:60px;right:20px;z-index:9999;max-width:360px;display:none;"></div>
    <div class="wrapper">

        @includeIf('layouts.header')

        @includeIf('layouts.sidebar')

        <!-- Content Wrapper. Contains page content -->
        <div class="content-wrapper">
            <!-- Content Header (Page header) -->
            <section class="content-header">
                <h1>
                    @yield('title')
                </h1>
                <ol class="breadcrumb">
                    @section('breadcrumb')
                        <li><a href="{{ url('/') }}"><i class="fa fa-dashboard"></i> Home</a></li>
                    @show
                </ol>
            </section>

            <!-- Main content -->
            <section class="content">
                @if(session('error') || $errors->any())
                <div class="alert alert-danger alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                    {{ session('error') ?? $errors->first() }}
                </div>
                @endif
                @yield('content')

            </section>
            <!-- /.content -->
        </div>
        <!-- /.content-wrapper -->

        @includeIf('layouts.footer')
    </div>
    <!-- ./wrapper -->

    <!-- jQuery 3 -->
    <script src="{{ asset('AdminLTE-2/bower_components/jquery/dist/jquery.min.js') }}"></script>
    <!-- Bootstrap 3.3.7 -->
    <script src="{{ asset('AdminLTE-2/bower_components/bootstrap/dist/js/bootstrap.min.js') }}"></script>
    <!-- Moment -->
    <script src="{{ asset('AdminLTE-2/bower_components/moment/min/moment.min.js') }}"></script>

    <!-- DataTables -->
    <script src="{{ asset('AdminLTE-2/bower_components/datatables.net/js/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('AdminLTE-2/bower_components/datatables.net-bs/js/dataTables.bootstrap.min.js') }}"></script>
    <!-- AdminLTE App -->
    <script src="{{ asset('AdminLTE-2/dist/js/adminlte.min.js') }}"></script>
    <!-- Paired viewport-friendly horizontal scroll for .table-responsive -->
    <script src="{{ asset('js/table-responsive-hscroll.js') }}"></script>
    <!-- Validator -->
    <script src="{{ asset('js/validator.min.js') }}"></script>

    <script>
        function preview(selector, temporaryFile, width = 200)  {
            $(selector).empty();
            $(selector).append(`<img src="${window.URL.createObjectURL(temporaryFile)}" width="${width}">`);
        }
        
        // Function to update incomplete products count in header
        function updateIncompleteProductsCount() {
            @if(auth()->check() && (auth()->user()->hasModulePermission('inventory','read') || auth()->user()->hasRole('admin')))
            $.get('{{ route('produk.incomplete.count') }}')
                .done(function(response) {
                    const count = response.count || 0;
                    const headerBadge = $('#incomplete-count-header');
                    const headerText = $('#incomplete-count-text');
                    const notificationMenu = $('.notifications-menu');
                    
                    if (count > 0) {
                        headerBadge.text(count).show();
                        headerText.text(count);
                        notificationMenu.show();
                    } else {
                        headerBadge.hide();
                        notificationMenu.hide();
                    }
                    
                    // Also update sidebar badge if it exists
                    const sidebarContainer = $('#incomplete-count-container');
                    const sidebarBadge = $('#incomplete-count-badge');
                    
                    if (sidebarContainer.length) {
                        if (count > 0) {
                            if (sidebarBadge.length) {
                                sidebarBadge.text(count);
                            } else {
                                sidebarContainer.html('<small class="label label-warning" id="incomplete-count-badge">' + count + '</small>');
                            }
                        } else {
                            sidebarContainer.empty();
                        }
                    }
                })
                .fail(function() {
                    // Silently fail - don't disrupt user experience
                    console.log('Failed to update incomplete products count');
                });
            @endif
        }
        
        // Chat unread count polling + visible notification when new message arrives
        var lastChatUnreadCount = parseInt('{{ $chatUnreadCount ?? 0 }}', 10) || 0;
        var chatPollUrl = '{{ route("chat.unread-count") }}';
        var chatPageUrl = '{{ url("/chat") }}';
        function updateChatUnreadCount() {
            $.ajax({
                url: chatPollUrl,
                type: 'GET',
                dataType: 'json'
            }).done(function(res) {
                var count = parseInt(res.count, 10) || 0;
                var badge = $('#chat-unread-badge');
                var wrap = $('#chat-unread-wrap');
                if (count > 0) {
                    if (badge.length) badge.text(count);
                    if (wrap.length) wrap.show();
                    var isNew = count > lastChatUnreadCount;
                    if (isNew && lastChatUnreadCount >= 0) {
                        var from = (res.last_from && res.last_from.name) ? res.last_from.name : 'Someone';
                        var msg = 'New message from ' + from;
                        var box = $('<div class="alert alert-info" style="margin:0;box-shadow:0 2px 10px rgba(0,0,0,.2);">' +
                            '<strong><i class="fa fa-comments"></i> ' + msg + '</strong><br>' +
                            '<a href="' + chatPageUrl + '" class="btn btn-primary btn-sm" style="margin-top:8px;">Open Chat</a></div>');
                        var container = $('#chat-notification-container');
                        container.empty().append(box).show();
                        if (typeof Notification !== 'undefined' && Notification.permission === 'granted') {
                            try { new Notification('New message', { body: msg }); } catch (e) {}
                        }
                    }
                } else {
                    if (wrap.length) wrap.hide();
                    $('#chat-notification-container').empty().hide();
                }
                lastChatUnreadCount = count;
            }).fail(function() {});
        }
        $(document).ready(function() {
            updateIncompleteProductsCount();
            setInterval(updateIncompleteProductsCount, 30000);
            if (chatPollUrl) {
                updateChatUnreadCount();
                setInterval(updateChatUnreadCount, 10000);
            }
        });
    </script>
    @stack('scripts')
</body>
</html>
