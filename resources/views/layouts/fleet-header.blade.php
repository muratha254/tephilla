<header class="fleet-topbar">
    <div class="fleet-topbar-left">
        <button type="button" class="fleet-icon-btn" id="fleet-sidebar-toggle" aria-label="Toggle menu">
            <i class="fa fa-bars"></i>
        </button>
        <button type="button" class="fleet-pill-btn"><i class="fa fa-globe"></i> English <i class="fa fa-caret-down"></i></button>
    </div>
    <div class="fleet-topbar-right">
        <div class="fleet-notify-badge">
            <button type="button" class="fleet-icon-btn" aria-label="Notifications">
                <i class="fa fa-bell-o"></i>
            </button>
            <span class="count">{{ $notificationCount ?? 0 }}</span>
        </div>
        <span class="fleet-user-menu">
            <i class="fa fa-user-circle"></i>
            Welcome, <strong>{{ auth()->user()->name }}</strong>
            <i class="fa fa-caret-down"></i>
        </span>
        <form action="{{ route('logout') }}" method="post" style="display:inline;">
            @csrf
            <button type="submit" class="fleet-icon-btn" title="Logout" aria-label="Logout">
                <i class="fa fa-sign-out"></i>
            </button>
        </form>
    </div>
</header>
