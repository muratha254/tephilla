@php
    $active = $activeMenu ?? 'dashboard';
    $open = $openMenu ?? null;

    $menu = [
        ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'fa-dashboard', 'url' => route('dashboard')],
        ['key' => 'availability', 'label' => 'Availability', 'icon' => 'fa-calendar-check-o', 'url' => route('availability')],
        [
            'key' => 'vehicle',
            'label' => 'Vehicle',
            'icon' => 'fa-truck',
            'submenu' => [
                ['key' => 'vehicle-list', 'label' => 'Vehicle List', 'url' => route('vehicles.index')],
                ['key' => 'vehicle-add', 'label' => 'Add Vehicle', 'url' => route('vehicles.create')],
                ['key' => 'vehicle-group', 'label' => 'Vehicle Group', 'url' => route('vehicle-groups.index')],
                ['key' => 'vehicle-route', 'label' => 'Route', 'url' => '#'],
            ],
        ],
        [
            'key' => 'vendors',
            'label' => "Vehicle Vendor's",
            'icon' => 'fa-building-o',
            'submenu' => [
                ['key' => 'vendor-list', 'label' => 'Vehicle Vendors List', 'url' => route('vehicle-vendors.index')],
                ['key' => 'vendor-add', 'label' => 'Add Vehicle Vendors', 'url' => route('vehicle-vendors.create')],
            ],
        ],
        [
            'key' => 'drivers',
            'label' => 'Drivers',
            'icon' => 'fa-id-card-o',
            'submenu' => [
                ['key' => 'driver-list', 'label' => 'Driver List', 'url' => route('drivers.index')],
                ['key' => 'driver-add', 'label' => 'Add Driver', 'url' => route('drivers.create')],
                ['key' => 'driver-performance', 'label' => 'Driver Performance', 'url' => route('drivers.performance')],
            ],
        ],
        [
            'key' => 'trips',
            'label' => 'Trips',
            'icon' => 'fa-road',
            'submenu' => [
                ['key' => 'trip-list', 'label' => 'Trips List', 'url' => route('trips.index')],
                ['key' => 'trip-add', 'label' => 'Add Trips', 'url' => route('trips.create')],
                ['key' => 'trip-dispatch', 'label' => 'Smart Dispatch', 'url' => route('trips.smart-dispatch'), 'badge' => 'AI'],
            ],
        ],
        [
            'key' => 'customer',
            'label' => 'Customer',
            'icon' => 'fa-users',
            'submenu' => [
                ['key' => 'customer-list', 'label' => 'Management', 'url' => route('customers.index')],
                ['key' => 'customer-add', 'label' => 'Add Customer', 'url' => route('customers.create')],
            ],
        ],
        [
            'key' => 'payments',
            'label' => 'Payment',
            'icon' => 'fa-credit-card',
            'submenu' => [
                ['key' => 'payments-list', 'label' => 'Customer Payments', 'url' => route('payments.index')],
                ['key' => 'payments-history', 'label' => 'Payment History', 'url' => route('payments.history')],
            ],
        ],
        [
            'key' => 'maintenance',
            'label' => 'Maintenance',
            'icon' => 'fa-wrench',
            'submenu' => [
                ['key' => 'maintenance-list', 'label' => 'Maintenance', 'url' => route('maintenance.index')],
                ['key' => 'maintenance-pms', 'label' => 'PMS Scheduler', 'url' => route('maintenance.pms.index')],
                ['key' => 'maintenance-incidents', 'label' => 'Incident Reports', 'url' => route('incidents.index')],
                ['key' => 'maintenance-tyre', 'label' => 'Tyre Mgmt', 'url' => route('tyres.index')],
                ['key' => 'maintenance-cost', 'label' => 'Cost Analytics', 'url' => route('maintenance.cost-analytics')],
                ['key' => 'maintenance-add', 'label' => 'Add Maintenance', 'url' => route('maintenance.create')],
                ['key' => 'maintenance-mechanic', 'label' => 'Mechanic', 'url' => route('mechanics.index')],
                ['key' => 'maintenance-vendor', 'label' => 'Vendor', 'url' => route('vehicle-vendors.index')],
            ],
        ],
        [
            'key' => 'parts',
            'label' => 'Parts Stock',
            'icon' => 'fa-cubes',
            'submenu' => [
                ['key' => 'parts-list', 'label' => 'Stock List', 'url' => route('stock.index')],
                ['key' => 'parts-add', 'label' => 'Add Stock', 'url' => route('stock.create')],
                ['key' => 'parts-purchases', 'label' => 'Purchase History', 'url' => route('stock.purchases')],
            ],
        ],
        [
            'key' => 'fuel',
            'label' => 'Fuel',
            'icon' => 'fa-tint',
            'submenu' => [
                ['key' => 'fuel-list', 'label' => 'Fuel Management', 'url' => route('fuel.index')],
                ['key' => 'fuel-add', 'label' => 'Add Fuel', 'url' => route('fuel.create')],
                ['key' => 'fuel-vendor', 'label' => 'Fuel Vendor', 'url' => route('fuel-vendors.index')],
            ],
        ],
        [
            'key' => 'reminder',
            'label' => 'Reminder',
            'icon' => 'fa-bell-o',
            'submenu' => [
                ['key' => 'reminder-list', 'label' => 'Reminder Management', 'url' => route('reminders.index')],
                ['key' => 'reminder-calendar', 'label' => 'Calendar View', 'url' => '#'],
                ['key' => 'reminder-add', 'label' => 'Add Reminder', 'url' => route('reminders.create')],
                ['key' => 'reminder-services', 'label' => 'Services', 'url' => '#'],
            ],
        ],
        [
            'key' => 'accounts',
            'label' => 'Accounts',
            'icon' => 'fa-book',
            'submenu' => [
                ['key' => 'accounts-categories', 'label' => 'Income/Expense Categories', 'url' => route('account-categories.index')],
            ],
        ],
        [
            'key' => 'tracking',
            'label' => 'Tracking',
            'icon' => 'fa-map-marker',
            'submenu' => [
                ['key' => 'tracking-live', 'label' => 'Live Fleet', 'url' => route('tracking.live')],
                ['key' => 'tracking-history', 'label' => 'History Tracking', 'url' => route('tracking.playback')],
            ],
        ],
        [
            'key' => 'reports',
            'label' => 'Reports',
            'icon' => 'fa-bar-chart',
            'submenu' => [
                ['key' => 'reports-booking', 'label' => 'Trips', 'url' => url('/reports/booking')],
                ['key' => 'reports-income', 'label' => 'Income & Expenses', 'url' => url('/reports/income')],
                ['key' => 'reports-fuel', 'label' => 'Fuel', 'url' => url('/reports/fuel')],
                ['key' => 'reports-driver', 'label' => 'Driver', 'url' => url('/reports/driver')],
                ['key' => 'reports-coupon', 'label' => 'Coupon', 'url' => '#'],
                ['key' => 'reports-reminders', 'label' => 'Reminders', 'url' => url('/reports/reminders')],
                ['key' => 'reports-maintenance', 'label' => 'Maintenance', 'url' => url('/reports/maintenance')],
            ],
        ],
        [
            'key' => 'employee',
            'label' => 'Employee',
            'icon' => 'fa-users',
            'submenu' => [
                ['key' => 'employee-list', 'label' => 'Employee Management', 'url' => route('employees.index')],
                ['key' => 'employee-add', 'label' => 'Add Employee', 'url' => route('employees.create')],
            ],
        ],
        [
            'key' => 'settings',
            'label' => 'Settings',
            'icon' => 'fa-cog',
            'url' => route('settings.general'),
            'submenu' => [
                ['key' => 'settings-general', 'label' => 'General Settings', 'url' => route('settings.general')],
                ['key' => 'settings-cron', 'label' => 'Cron Settings', 'url' => route('settings.placeholder', 'cron')],
                ['key' => 'settings-menu_ordering', 'label' => 'Menu Ordering', 'url' => route('settings.placeholder', 'menu-ordering')],
                ['key' => 'settings-languages', 'label' => 'Languages', 'url' => route('settings.placeholder', 'languages')],
                ['key' => 'settings-smtp', 'label' => 'SMTP Configuration', 'url' => route('settings.smtp')],
                ['key' => 'settings-email_template', 'label' => 'Email Template', 'url' => route('settings.placeholder', 'email-template')],
                ['key' => 'settings-sms', 'label' => 'SMS Configuration', 'url' => route('settings.placeholder', 'sms')],
            ],
        ],
    ];

    $isOpen = function (array $item) use ($active, $open) {
        if (! empty($item['submenu'])) {
            return $open === $item['key'] || collect($item['submenu'])->contains(fn ($s) => $s['key'] === $active);
        }

        return $active === $item['key'];
    };
@endphp

<aside class="fleet-sidebar" id="fleet-sidebar">
    <div class="fleet-brand">
        <span class="fleet-brand-logo"><i class="fa fa-truck"></i></span>
        <span class="fleet-brand-text">{{ $systemName ?? fleet_system_name() }}</span>
    </div>
    <ul class="fleet-menu">
        @foreach ($menu as $item)
            @if (! empty($item['submenu']))
                @php $expanded = $isOpen($item); @endphp
                <li class="has-submenu {{ $expanded ? 'open active' : '' }}">
                    <a href="{{ $item['url'] ?? '#' }}" class="fleet-submenu-toggle">
                        <i class="fa {{ $item['icon'] }}"></i>
                        <span class="menu-label">{{ $item['label'] }}</span>
                        <i class="fa fa-angle-left menu-caret"></i>
                    </a>
                    <ul class="fleet-submenu">
                        @foreach ($item['submenu'] as $sub)
                            <li class="{{ $active === $sub['key'] ? 'active' : '' }}">
                                <a href="{{ $sub['url'] }}" @if(($sub['url'] ?? '#') === '#') onclick="return false;" @endif>
                                    {{ $sub['label'] }}
                                    @if (! empty($sub['badge']))
                                        <span class="fleet-menu-badge">{{ $sub['badge'] }}</span>
                                    @endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </li>
            @else
                <li class="{{ $active === $item['key'] ? 'active' : '' }}">
                    <a href="{{ $item['url'] }}">
                        <i class="fa {{ $item['icon'] }}"></i>
                        <span class="menu-label">{{ $item['label'] }}</span>
                        @if (! empty($item['children']))
                            <i class="fa fa-angle-left menu-caret"></i>
                        @endif
                    </a>
                </li>
            @endif
        @endforeach
    </ul>
</aside>
