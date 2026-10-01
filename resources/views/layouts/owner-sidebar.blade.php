<aside class="fleet-sidebar" id="fleet-sidebar">
    <div class="fleet-brand">
        <span class="fleet-brand-text">{{ $systemShortName ?? $systemName ?? fleet_system_short_name() }}</span>
    </div>
    <div class="sellix-user-panel">
        <div class="sellix-user-avatar" aria-hidden="true"><i class="fa fa-shield"></i></div>
        <div class="sellix-user-name">{{ auth()->user()->name }}</div>
    </div>
    <ul class="fleet-menu">
        <li class="sx-signout">
            <form action="{{ route('logout') }}" method="post">
                @csrf
                <button type="submit">
                    <i class="fa fa-sign-out"></i>
                    <span class="menu-label">Sign out</span>
                </button>
            </form>
        </li>
    </ul>
</aside>
