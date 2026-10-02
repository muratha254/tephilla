@php
    $active = $activeMenu ?? '';
    $user = auth()->user();
    $can = function ($permission) use ($user) {
        return $user && $user->hasPermission($permission);
    };
    $item = function ($key, $label, $icon, $url, $show) use ($active) {
        return compact('key', 'label', 'icon', 'url', 'show') + ['active' => $active === $key || strpos((string) $active, $key) === 0];
    };
    $childActive = function ($key) use ($active) {
        $active = (string) $active;

        return $active === $key || strpos($active, $key) === 0;
    };
    $itemChildren = [
        ['key' => 'products', 'label' => 'Products', 'url' => route('products.index'), 'show' => $can('products.view')],
        ['key' => 'colours', 'label' => 'Colours', 'url' => route('colours.index'), 'show' => $can('products.view')],
        ['key' => 'categories', 'label' => 'Categories', 'url' => route('categories.index'), 'show' => $can('categories.view')],
        ['key' => 'units', 'label' => 'Units', 'url' => route('units.index'), 'show' => $can('units.view')],
        ['key' => 'stock.manager', 'label' => 'Inventory', 'url' => route('stock.manager'), 'show' => $can('inventory.view')],
        ['key' => 'stock.opening', 'label' => 'Opening stock', 'url' => route('stock.opening'), 'show' => $can('inventory.adjust')],
        ['key' => 'reports.stock.ledger', 'label' => 'Stock ledger', 'url' => route('reports.stock.ledger'), 'show' => $can('reports.stock') || $can('reports.view')],
    ];
    foreach ($itemChildren as &$child) {
        $child['active'] = $childActive($child['key']);
    }
    unset($child);
    $itemsOpen = collect($itemChildren)->contains(fn ($child) => ! empty($child['active']));
    $reportLink = function ($key, $label, $routeName, $show) use ($active) {
        return [
            'key' => $key,
            'label' => $label,
            'url' => route($routeName),
            'show' => $show,
            'active' => $active === $key,
        ];
    };
    $reportGroup = function ($key, $label, array $children) {
        $open = collect($children)->contains(fn ($child) => ! empty($child['active']));

        return [
            'key' => $key,
            'label' => $label,
            'show' => collect($children)->contains(fn ($child) => ! empty($child['show'])),
            'open' => $open,
            'active' => $open,
            'children' => $children,
        ];
    };
    $reportChildren = [
        $reportGroup('reports.summary', 'Summary Reports', [
            $reportLink('reports.summary.daily', 'Daily Summary', 'reports.summary.daily', $can('reports.view')),
            $reportLink('reports.summary.employee-branch', 'Employee Sales Summary', 'reports.summary.employee-branch', $can('reports.view')),
            $reportLink('reports.summary.branch', 'Branch Sales Summary', 'reports.summary.branch', $can('reports.view')),
            $reportLink('reports.summary.z', 'Z-Report', 'reports.summary.z', $can('reports.view')),
            $reportLink('reports.summary.debtors-creditors', 'Debtors | Creditors', 'reports.summary.debtors-creditors', $can('reports.view')),
        ]),
        $reportGroup('reports.tax', 'Tax Reports', [
            $reportLink('reports.tax.index', 'Tax Report', 'reports.tax.index', $can('reports.tax')),
            $reportLink('reports.tax.vat', 'VAT Report', 'reports.tax.vat', $can('reports.tax')),
            $reportLink('reports.tax.sales-vat', 'Sales VAT', 'reports.tax.sales-vat', $can('reports.tax')),
            $reportLink('reports.tax.monthly-vat', 'Monthly VAT', 'reports.tax.monthly-vat', $can('reports.tax')),
        ]),
        $reportGroup('reports.sales', 'Sales Reports', [
            $reportLink('reports.sales.sales', 'Sales Report', 'reports.sales.sales', $can('reports.sales')),
            $reportLink('reports.sales.sales-custom', 'Sales Report Custom', 'reports.sales.sales-custom', $can('reports.sales')),
            $reportLink('reports.sales.sales-summary', 'Sales Summary', 'reports.sales.sales-summary', $can('reports.sales')),
            $reportLink('reports.sales.sales-employees', 'Sales by Employee', 'reports.sales.sales-employees', $can('reports.sales')),
            $reportLink('reports.sales.item-sales', 'Item Sales', 'reports.sales.item-sales', $can('reports.sales')),
            $reportLink('reports.sales.item-sales-summary', 'Item Sales Summary', 'reports.sales.item-sales-summary', $can('reports.sales')),
            $reportLink('reports.sales.items-category-summary', 'Items Category Sales', 'reports.sales.items-category-summary', $can('reports.sales')),
            $reportLink('reports.sales.payments', 'Sales Payments', 'reports.sales.payments', $can('reports.sales')),
            $reportLink('reports.sales.commission', 'Sales Commission', 'reports.sales.commission', $can('reports.sales')),
            $reportLink('reports.sales.returns', 'Sales Returns', 'reports.sales.returns', $can('reports.sales')),
            $reportLink('reports.sales.cancelled', 'Cancelled Sales', 'reports.sales.cancelled', $can('reports.sales')),
            $reportLink('reports.sales.complementary', 'Complementary Sales', 'reports.sales.complementary', $can('reports.sales')),
            $reportLink('reports.sales.credit-aging', 'Credit Sales Aging', 'reports.sales.credit-aging', $can('reports.sales')),
            $reportLink('reports.sales.employee-clearance', 'Employee Clearance', 'reports.sales.employee-clearance', $can('reports.sales')),
            $reportLink('reports.sales.cashier-clearance', 'Cashier Clearance', 'reports.sales.cashier-clearance', $can('reports.sales')),
        ]),
        $reportGroup('reports.purchases', 'Purchase Reports', [
            $reportLink('reports.purchases.purchase', 'Purchase Report', 'reports.purchases.purchase', $can('reports.purchases')),
            $reportLink('reports.purchases.items', 'Items Purchase', 'reports.purchases.items', $can('reports.purchases')),
            $reportLink('reports.purchases.payments', 'Purchase Payments', 'reports.purchases.payments', $can('reports.purchases')),
        ]),
        $reportGroup('reports.stock', 'Stock/Products Reports', [
            $reportLink('reports.stock.stock', 'Stock Report', 'reports.stock.stock', $can('reports.stock')),
            $reportLink('reports.stock.stock-as-at', 'Stock as at Date', 'reports.stock.stock-as-at', $can('reports.stock')),
            $reportLink('reports.stock.stock-as-at-detailed', 'Stock as at Date Detailed', 'reports.stock.stock-as-at-detailed', $can('reports.stock')),
            $reportLink('reports.stock.price-list', 'Price List', 'reports.stock.price-list', $can('reports.stock')),
            $reportLink('reports.stock.ledger', 'Items Ledger', 'reports.stock.ledger', $can('reports.stock')),
            $reportLink('reports.stock.valuation', 'Items Valuation', 'reports.stock.valuation', $can('reports.stock')),
            $reportLink('reports.stock.alert', 'Stock Alert', 'reports.stock.alert', $can('reports.stock')),
            $reportLink('reports.stock.template', 'Stock Template', 'reports.stock.template', $can('reports.stock')),
            $reportLink('reports.stock.transfer', 'Stock Transfer', 'reports.stock.transfer', $can('reports.stock')),
            $reportLink('reports.stock.adjust', 'Stock Adjustment', 'reports.stock.adjust', $can('reports.stock')),
            $reportLink('reports.stock.damaged', 'Damaged Products', 'reports.stock.damaged', $can('reports.stock')),
            $reportLink('reports.stock.issued', 'Issued Products', 'reports.stock.issued', $can('reports.stock')),
            $reportLink('reports.stock.consumption', 'Consumption', 'reports.stock.consumption', $can('reports.stock')),
            $reportLink('reports.stock.production', 'Production', 'reports.stock.production', $can('reports.stock')),
        ]),
        $reportLink('reports.expenses', 'Expense Report', 'reports.expenses', $can('reports.expenses')),
        $reportLink('reports.suppliers', 'Suppliers Report', 'reports.suppliers', $can('reports.view')),
        $reportLink('reports.loyalty', 'Loyalty Points Report', 'reports.loyalty', $can('reports.view')),
        $reportLink('reports.expired', 'Expired Items Report', 'reports.expired', $can('reports.stock')),
        $reportGroup('reports.customers', 'Customer Reports', [
            $reportLink('reports.customers.customers', 'Customers Report', 'reports.customers.customers', $can('reports.customers')),
            $reportLink('reports.customers.statement', 'Customer Statement', 'reports.customers.statement', $can('reports.customers')),
            $reportLink('reports.customers.purchases', 'Customer Purchases', 'reports.customers.purchases', $can('reports.customers')),
        ]),
        $reportLink('reports.user-logs', 'User Logs', 'reports.user-logs', $can('reports.audit')),
        $reportLink('reports.audit', 'Audit Trail Report', 'reports.audit', $can('reports.audit')),
    ];
    $reportsOpen = collect($reportChildren)->contains(fn ($child) => ! empty($child['active']) || ! empty($child['open']));
    $salesChildren = [
        ['key' => 'sales.pos', 'label' => 'POS', 'icon' => 'fa-shopping-cart', 'url' => route('pos.index'), 'show' => $can('pos.view'), 'active' => $active === 'sales.pos'],
        ['key' => 'sales.index', 'label' => 'Sales List', 'icon' => 'fa-list', 'url' => route('sales.index'), 'show' => $can('sales.view'), 'active' => $active === 'sales.index'],
        ['key' => 'sales.returns', 'label' => 'Sales Return', 'icon' => 'fa-refresh', 'url' => route('sales.returns'), 'show' => $can('sales.return'), 'active' => $childActive('sales.returns')],
        ['key' => 'sales.voids', 'label' => 'Cancelled Sales (Voids)', 'icon' => 'fa-times-circle', 'url' => route('sales.voids'), 'show' => $can('sales.void') || $can('pos.void'), 'active' => $active === 'sales.voids'],
        ['key' => 'sales.credit-notes', 'label' => 'Credit Notes', 'icon' => 'fa-file-text-o', 'url' => route('sales.credit-notes'), 'show' => $can('sales.view'), 'active' => $childActive('sales.credit-notes')],
        ['key' => 'quotations', 'label' => 'Quotations', 'icon' => 'fa-file-text-o', 'url' => route('quotations.index'), 'show' => $can('quotations.view'), 'active' => $childActive('quotations')],
        ['key' => 'sales.invoices', 'label' => 'Invoices', 'icon' => 'fa-file-text-o', 'url' => route('sales.invoices'), 'show' => $can('sales.view'), 'active' => $active === 'sales.invoices'],
        ['key' => 'sales.orders', 'label' => 'Order Screen', 'icon' => 'fa-desktop', 'url' => route('sales.orders'), 'show' => $can('pos.view') || $can('pos.operate') || $can('sales.create'), 'active' => $active === 'sales.orders'],
    ];
    $salesOpen = collect($salesChildren)->contains(fn ($child) => ! empty($child['active']));

    $menu = [
        $item('dashboard', 'Dashboard', 'fa-dashboard', route('dashboard'), true),
        [
            'key' => 'items',
            'label' => 'Items/Products',
            'icon' => 'fa-cubes',
            'show' => collect($itemChildren)->contains(fn ($child) => ! empty($child['show'])),
            'open' => $itemsOpen,
            'active' => $itemsOpen,
            'children' => $itemChildren,
        ],
        [
            'key' => 'folding',
            'label' => 'Folding',
            'icon' => 'fa-industry',
            'show' => $can('inventory.view') || $can('inventory.adjust'),
            'open' => strpos((string) $active, 'folding') === 0,
            'active' => strpos((string) $active, 'folding') === 0,
            'children' => [
                ['key' => 'folding.create', 'label' => 'Production', 'url' => route('folding.create'), 'show' => $can('inventory.adjust'), 'active' => $active === 'folding.create'],
                ['key' => 'folding.index', 'label' => 'Folding list', 'url' => route('folding.index'), 'show' => $can('inventory.view'), 'active' => $active === 'folding.index'],
                ['key' => 'folding.flat-sheet', 'label' => 'Flat sheet stock', 'url' => route('folding.flat-sheet'), 'show' => $can('inventory.view'), 'active' => $active === 'folding.flat-sheet'],
            ],
        ],
        [
            'key' => 'purchases',
            'label' => 'Purchases',
            'icon' => 'fa-money',
            'show' => $can('purchases.view') || $can('purchases.create'),
            'open' => strpos((string) $active, 'purchases') === 0,
            'active' => strpos((string) $active, 'purchases') === 0,
            'children' => [
                ['key' => 'purchases.index', 'label' => 'Purchase List', 'url' => route('purchases.index'), 'show' => $can('purchases.view'), 'active' => $active === 'purchases.index'],
                ['key' => 'purchases.schedule', 'label' => 'Payment Schedule', 'url' => route('purchases.index', ['view' => 'schedule']), 'show' => $can('purchases.view'), 'active' => $active === 'purchases.schedule'],
                ['key' => 'purchases.direct', 'label' => 'Direct Purchase', 'url' => route('purchases.create', ['type' => 'direct']), 'show' => $can('purchases.create'), 'active' => $active === 'purchases.direct'],
                ['key' => 'purchases.lpo', 'label' => 'LPO List', 'url' => route('purchases.index', ['view' => 'lpo']), 'show' => $can('purchases.view'), 'active' => $active === 'purchases.lpo'],
                ['key' => 'purchases.lpo.create', 'label' => 'New LPO', 'url' => route('purchases.create', ['type' => 'lpo']), 'show' => $can('purchases.create'), 'active' => $active === 'purchases.lpo.create'],
            ],
        ],
        $item('stock.transfers', 'Transfers', 'fa-exchange', route('stock.transfers.index'), $can('inventory.transfer') || $can('inventory.view')),
        [
            'key' => 'sales',
            'label' => 'Sales',
            'icon' => 'fa-shopping-cart',
            'show' => collect($salesChildren)->contains(fn ($child) => ! empty($child['show'])),
            'open' => $salesOpen,
            'active' => $salesOpen,
            'children' => $salesChildren,
        ],
        $item('customers', 'Customers', 'fa-users', route('customers.index'), $can('customers.view')),
        $item('suppliers', 'Suppliers', 'fa-truck', route('suppliers.index'), $can('suppliers.view')),
        [
            'key' => 'expenses',
            'label' => 'Expenses',
            'icon' => 'fa-credit-card',
            'show' => $can('expenses.view') || $can('expenses.create'),
            'open' => strpos((string) $active, 'expenses') === 0,
            'active' => strpos((string) $active, 'expenses') === 0,
            'children' => [
                ['key' => 'expenses.create', 'label' => 'New Expense', 'url' => route('expenses.create'), 'show' => $can('expenses.create'), 'active' => $active === 'expenses.create'],
                ['key' => 'expenses.index', 'label' => 'Expenses List', 'url' => route('expenses.index'), 'show' => $can('expenses.view'), 'active' => $active === 'expenses.index'],
            ],
        ],
        [
            'key' => 'accounting',
            'label' => 'Accounting',
            'icon' => 'fa-book',
            'show' => $can('accounting.view'),
            'open' => strpos((string) $active, 'accounting') === 0,
            'active' => strpos((string) $active, 'accounting') === 0,
            'children' => [
                ['key' => 'accounting.types', 'label' => 'Accounts Type', 'url' => route('accounting.types'), 'show' => true, 'active' => $active === 'accounting.types'],
                ['key' => 'accounting.sub-types', 'label' => 'Sub-Accounts Type', 'url' => route('accounting.sub-types'), 'show' => true, 'active' => $active === 'accounting.sub-types'],
                ['key' => 'accounting.chart', 'label' => 'Chart of Accounts', 'url' => route('accounting.chart'), 'show' => true, 'active' => $active === 'accounting.chart'],
                ['key' => 'accounting.balances', 'label' => 'Accounts Balances', 'url' => route('accounting.balances'), 'show' => true, 'active' => $active === 'accounting.balances'],
                ['key' => 'accounting.money', 'label' => 'Money', 'url' => route('accounting.money'), 'show' => true, 'active' => $active === 'accounting.money'],
                ['key' => 'accounting.journal', 'label' => 'Journal Entry', 'url' => route('accounting.journal'), 'show' => true, 'active' => $active === 'accounting.journal'],
                ['key' => 'accounting.profit-loss', 'label' => 'Profit & Loss', 'url' => route('accounting.profit-loss'), 'show' => true, 'active' => $active === 'accounting.profit-loss'],
                ['key' => 'accounting.balance-sheet', 'label' => 'Balance Sheet', 'url' => route('accounting.balance-sheet'), 'show' => true, 'active' => $active === 'accounting.balance-sheet'],
                ['key' => 'accounting.trial-balance', 'label' => 'Trial Balance', 'url' => route('accounting.trial-balance'), 'show' => true, 'active' => $active === 'accounting.trial-balance'],
                ['key' => 'accounting.combined-gl', 'label' => 'Combined GL', 'url' => route('accounting.combined-gl'), 'show' => true, 'active' => $active === 'accounting.combined-gl'],
                ['key' => 'accounting.customers', 'label' => 'Customers Balances', 'url' => route('accounting.customers'), 'show' => true, 'active' => $active === 'accounting.customers'],
                ['key' => 'accounting.suppliers', 'label' => 'Suppliers Balances', 'url' => route('accounting.suppliers'), 'show' => true, 'active' => $active === 'accounting.suppliers'],
            ],
        ],
        [
            'key' => 'reports',
            'label' => 'Reports',
            'icon' => 'fa-bar-chart',
            'show' => collect($reportChildren)->contains(fn ($child) => ! empty($child['show'])),
            'open' => $reportsOpen,
            'active' => $reportsOpen,
            'children' => $reportChildren,
        ],
        $item('users', 'Users', 'fa-user', route('users.index'), $can('users.view')),
        [
            'key' => 'settings',
            'label' => 'Settings',
            'icon' => 'fa-cog',
            'show' => true,
            'open' => strpos((string) $active, 'settings') === 0 || $active === 'billing.index',
            'active' => strpos((string) $active, 'settings') === 0 || $active === 'billing.index',
            'children' => [
                $reportLink('settings.company', 'Company Profile', 'settings.company', $can('settings.view') || $can('settings.company')),
                [
                    'key' => 'billing.index',
                    'label' => 'Billing',
                    'url' => route('billing.index'),
                    'show' => $user && ! $user->isSystemOwner() && ($user->isCompanyAdmin() || $can('settings.view') || $can('settings.company')),
                    'active' => $active === 'billing.index',
                ],
                $reportLink('settings.branches', 'Manage Branch', 'settings.branches', $can('settings.branches') || $can('branches.view')),
                $reportLink('settings.general', 'Site Settings', 'settings.general', $can('settings.view')),
                $reportLink('settings.tax', 'Tax List', 'settings.tax', $can('settings.view')),
                [
                    'key' => 'settings.salutation',
                    'label' => 'Salutation',
                    'url' => route('settings.lookups', 'salutations'),
                    'show' => $can('settings.view'),
                    'active' => $active === 'settings.salutation',
                ],
                [
                    'key' => 'settings.progress',
                    'label' => 'Progress Status',
                    'url' => route('settings.lookups', 'progress-statuses'),
                    'show' => $can('settings.view'),
                    'active' => $active === 'settings.progress',
                ],
                $reportGroup('settings.places', 'Places', [
                    [
                        'key' => 'settings.places.counties',
                        'label' => 'Counties',
                        'url' => route('settings.lookups', 'counties'),
                        'show' => $can('settings.view'),
                        'active' => $active === 'settings.places.counties',
                    ],
                    [
                        'key' => 'settings.places.cities',
                        'label' => 'Cities / Towns',
                        'url' => route('settings.lookups', 'cities'),
                        'show' => $can('settings.view'),
                        'active' => $active === 'settings.places.cities',
                    ],
                ]),
                [
                    'key' => 'settings.currency',
                    'label' => 'Currency List',
                    'url' => route('settings.lookups', 'currencies'),
                    'show' => $can('settings.view'),
                    'active' => $active === 'settings.currency',
                ],
                $reportLink('settings.password', 'Change Password', 'settings.password', true),
                $reportLink('settings.audit', 'Audit Trail', 'settings.audit', $can('settings.view')),
                $reportLink('settings.backup', 'Database Backup', 'settings.backup', $can('settings.view')),
            ],
        ],
    ];
@endphp

<aside class="fleet-sidebar" id="fleet-sidebar">
    <div class="fleet-brand">
        <span class="fleet-brand-text">{{ $systemShortName ?? $systemName ?? fleet_system_short_name() }}</span>
    </div>

    <div class="sellix-user-panel">
        <div class="sellix-user-avatar" aria-hidden="true">
            <i class="fa fa-user"></i>
        </div>
        <div class="sellix-user-name">{{ auth()->user()->name }}</div>
    </div>

    <ul class="fleet-menu">
        @foreach ($menu as $row)
            @if(!empty($row['show']))
                @if(!empty($row['children']))
                    <li class="has-submenu {{ !empty($row['open']) ? 'open' : '' }} {{ !empty($row['active']) ? 'active' : '' }}">
                        <a href="#" class="fleet-submenu-toggle">
                            <i class="fa {{ $row['icon'] }}"></i>
                            <span class="menu-label">{{ $row['label'] }}</span>
                            <i class="fa fa-angle-left menu-caret"></i>
                        </a>
                        <ul class="fleet-submenu">
                            @foreach($row['children'] as $child)
                                @if(!empty($child['show']))
                                    @if(!empty($child['children']))
                                        <li class="has-submenu {{ !empty($child['open']) ? 'open' : '' }} {{ !empty($child['active']) ? 'active' : '' }}">
                                            <a href="#" class="fleet-submenu-toggle">
                                                <i class="fa fa-circle"></i>
                                                <span>{{ $child['label'] }}</span>
                                                <i class="fa fa-angle-left menu-caret"></i>
                                            </a>
                                            <ul class="fleet-submenu">
                                                @foreach($child['children'] as $leaf)
                                                    @if(!empty($leaf['show']))
                                                        <li class="{{ !empty($leaf['active']) ? 'active' : '' }}">
                                                            <a href="{{ $leaf['url'] }}">
                                                                <i class="fa fa-circle"></i>
                                                                <span>{{ $leaf['label'] }}</span>
                                                            </a>
                                                        </li>
                                                    @endif
                                                @endforeach
                                            </ul>
                                        </li>
                                    @else
                                        <li class="{{ !empty($child['active']) ? 'active' : '' }}">
                                            <a href="{{ $child['url'] }}">
                                                <i class="fa {{ $child['icon'] ?? 'fa-circle' }}"></i>
                                                <span>{{ $child['label'] }}</span>
                                            </a>
                                        </li>
                                    @endif
                                @endif
                            @endforeach
                        </ul>
                    </li>
                @else
                    <li class="{{ !empty($row['active']) ? 'active' : '' }}">
                        <a href="{{ $row['url'] }}">
                            <i class="fa {{ $row['icon'] }}"></i>
                            <span class="menu-label">{{ $row['label'] }}</span>
                        </a>
                    </li>
                @endif
            @endif
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
