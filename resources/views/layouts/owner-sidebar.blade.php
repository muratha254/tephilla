@php
    $active = $activeMenu ?? 'owner.dashboard';
    $item = function ($key, $label, $icon, $url) use ($active) {
        return [
            'key' => $key,
            'label' => $label,
            'icon' => $icon,
            'url' => $url,
            'show' => true,
            'active' => $active === $key || strpos((string) $active, $key) === 0,
        ];
    };
    $menu = [
        $item('owner.dashboard', 'Dashboard', 'fa-dashboard', route('owner.dashboard')),
        $item('owner.pending', 'Pending requests', 'fa-hourglass-start', route('owner.businesses.index', ['status' => 'pending_approval'])),
        $item('owner.businesses', 'Businesses', 'fa-building', route('owner.businesses.index')),
        $item('owner.plans', 'Plans', 'fa-id-card', route('owner.plans.index')),
    ];
@endphp

<aside class="fleet-sidebar" id="fleet-sidebar">
    <div class="fleet-brand">
        <span class="fleet-brand-text">System Owner</span>
    </div>
    <div class="sellix-user-panel">
        <div class="sellix-user-avatar" aria-hidden="true"><i class="fa fa-shield"></i></div>
        <div class="sellix-user-name">{{ auth()->user()->name }}</div>
    </div>
    <ul class="fleet-menu">
        @foreach($menu as $row)
            <li class="{{ !empty($row['active']) ? 'active' : '' }}">
                <a href="{{ $row['url'] }}">
                    <i class="fa {{ $row['icon'] }}"></i>
                    <span class="menu-label">{{ $row['label'] }}</span>
                </a>
            </li>
        @endforeach
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
