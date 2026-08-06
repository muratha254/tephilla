<header class="main-header">
    <!-- Logo -->
    <a href="index2.html" class="logo">
        <!-- mini logo for sidebar mini 50x50 pixels -->
        @php
            $words = explode(' ', $setting->nama_perusahaan);
            $word  = '';
            foreach ($words as $w) {
                $word .= $w[0];
            }
        @endphp
        <span class="logo-mini">{{ $word }}</span>
        <!-- logo for regular state and mobile devices -->
        <span class="logo-lg"><b>{{ $setting->nama_perusahaan }}</b></span>
    </a>
    <!-- Header Navbar: style can be found in header.less -->
    <nav class="navbar navbar-static-top">
        <!-- Sidebar toggle button-->
        <a href="#" class="sidebar-toggle" data-toggle="push-menu" role="button">
            <span class="sr-only">Toggle navigation</span>
        </a>

        <div class="navbar-custom-menu">
            <ul class="nav navbar-nav">
                @php($u = auth()->user())
                @if($u && ($u->hasModulePermission('inventory','read') || $u->hasRole('admin')))
                <!-- Incomplete Products Notification -->
                <li class="dropdown notifications-menu">
                    <a href="#" class="dropdown-toggle" data-toggle="dropdown" id="incomplete-products-notification">
                        <i class="fa fa-exclamation-triangle"></i>
                        <span class="label label-warning" id="incomplete-count-header" style="display: none;">0</span>
                    </a>
                    <ul class="dropdown-menu">
                        <li class="header" id="incomplete-notification-header">You have <span id="incomplete-count-text">0</span> incomplete product(s)</li>
                        <li>
                            <ul class="menu">
                                <li>
                                    <a href="{{ route('produk.incomplete') }}">
                                        <i class="fa fa-warning text-warning"></i> View incomplete products to complete them
                                    </a>
                                </li>
                            </ul>
                        </li>
                        <li class="footer"><a href="{{ route('produk.incomplete') }}">View all incomplete products</a></li>
                    </ul>
                </li>
                @endif
                
                <!-- User Account: style can be found in dropdown.less -->
                <li class="dropdown user user-menu">
                    <a href="#" class="dropdown-toggle" data-toggle="dropdown">
                        <img src="{{ url(auth()->user()->foto ?? '') }}" class="user-image img-profil"
                            alt="User Image">
                        <span class="hidden-xs">{{ auth()->user()->name }}</span>
                    </a>
                    <ul class="dropdown-menu">
                        <!-- User image -->
                        <li class="user-header">
                            <img src="{{ url(auth()->user()->foto ?? '') }}" class="img-circle img-profil"
                                alt="User Image">

                            <p>
                                {{ auth()->user()->name }} - {{ auth()->user()->email }}
                            </p>
                        </li>
                        <!-- Menu Footer-->
                        <li class="user-footer">
                            <div class="pull-left">
                                <a href="{{ route('user.profil') }}" class="btn btn-primary btn-flat">My Profile</a>
                                <a href="#" class="btn btn-default btn-flat" onclick="event.preventDefault(); openSwitchUserModal()"><i class="fa fa-exchange"></i> Switch User</a>
                            </div>
                            <div class="pull-right">
                                <a href="#" class="btn btn-danger btn-flat"
                                    onclick="$('#logout-form').submit()"><i class="fa fa-power-off"></i> Logout</a>
                            </div>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </nav>
</header>

<form action="{{ route('logout') }}" method="post" id="logout-form" style="display: none;">
    @csrf
</form>

<!-- Switch User Modal -->
<div class="modal fade" id="modal-switch-user" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title"><i class="fa fa-exchange"></i> Switch User</h4>
            </div>
            <div class="modal-body">
                <p class="text-muted">Select a user to switch into their account:</p>
                <div id="switch-user-list" style="max-height: 400px; overflow-y: auto;">
                    <div class="text-center" style="padding: 20px;">
                        <i class="fa fa-spinner fa-spin fa-2x"></i>
                        <p>Loading users...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default btn-flat" data-dismiss="modal">
                    <i class="fa fa-times"></i> Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Switch User Password Modal -->
<div class="modal fade" id="modal-switch-password" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form id="switch-user-password-form" method="POST" action="">
                @csrf
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                    <h4 class="modal-title"><i class="fa fa-lock"></i> Enter Password</h4>
                </div>
                <div class="modal-body">
                    <p>Enter <strong id="switch-target-name"></strong>'s password to switch to their account:</p>
                    <div class="form-group">
                        <label for="switch-user-password">Password</label>
                        <input type="password" class="form-control" id="switch-user-password" name="password" required placeholder="Enter password" autocomplete="off">
                        <div id="switch-password-error" class="help-block text-danger" style="display: none;"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default btn-flat" data-dismiss="modal">
                        <i class="fa fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-primary btn-flat">
                        <i class="fa fa-sign-in"></i> Switch
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openSwitchUserModal() {
    $('#modal-switch-user').modal('show');
    loadSwitchUsers();
}

function loadSwitchUsers() {
    $('#switch-user-list').html('<div class="text-center" style="padding: 20px;"><i class="fa fa-spinner fa-spin fa-2x"></i><p>Loading users...</p></div>');
    
    $.get('{{ route("switch-user.users") }}', function(users) {
        if (users.length === 0) {
            $('#switch-user-list').html('<div class="alert alert-info">No other users available to switch to.</div>');
            return;
        }
        
        var html = '<div class="list-group">';
        users.forEach(function(user) {
            var roleLabel = user.role ? user.role.charAt(0).toUpperCase() + user.role.slice(1) : 'Cashier';
            var roleBadge = '';
            if (user.role === 'admin') {
                roleBadge = '<span class="label label-danger">Admin</span>';
            } else if (user.role === 'manager') {
                roleBadge = '<span class="label label-warning">Manager</span>';
            } else {
                roleBadge = '<span class="label label-info">Cashier</span>';
            }
            
            var escapedName = (user.name || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
            html += '<a href="#" class="list-group-item switch-user-item" data-id="' + user.id + '" data-name="' + escapedName + '">';
            html += '<h4 class="list-group-item-heading">';
            html += '<i class="fa fa-user"></i> ' + (user.name || '') + ' ' + roleBadge;
            html += '</h4>';
            html += '<p class="list-group-item-text text-muted">' + (user.email || '') + '</p>';
            html += '</a>';
        });
        html += '</div>';
        
        $('#switch-user-list').html(html);
        $('#switch-user-list').off('click', '.switch-user-item').on('click', '.switch-user-item', function(e) {
            e.preventDefault();
            var id = $(this).data('id');
            var name = $(this).data('name');
            showPasswordPrompt(id, name);
        });
    }).fail(function() {
        $('#switch-user-list').html('<div class="alert alert-danger"><i class="fa fa-exclamation-triangle"></i> Failed to load users. Please try again.</div>');
    });
}

function showPasswordPrompt(userId, userName) {
    $('#modal-switch-user').modal('hide');
    $('#switch-target-name').html(userName);
    $('#switch-user-password').val('');
    $('#switch-password-error').hide().text('');
    $('#switch-user-password-form').attr('action', '{{ url("/switch-user") }}/' + userId);
    $('#modal-switch-password').modal('show');
    setTimeout(function() { $('#switch-user-password').focus(); }, 500);
}

$(function() {
    $('#modal-switch-password').on('shown.bs.modal', function() {
        $('#switch-user-password').focus();
    });
    $('#switch-user-password-form').on('submit', function(e) {
        if (!$('#switch-user-password').val().trim()) {
            e.preventDefault();
            $('#switch-password-error').text('Please enter the password.').show();
            return;
        }
    });
});
</script>