@php
    $active = $activeMenu ?? 'dashboard';

    $user = auth()->user();
    $can = function ($permission) use ($user) {
        return $user && $user->hasPermission($permission);
    };

    $productOpen = in_array($active, [
        'products.create', 'products.index', 'products.labels', 'products.prices',
        'stock.manager', 'stock.alert', 'stock.issued', 'stock.conversion', 'stock.transfers',
        'categories.index', 'brands.index', 'units.index',
    ], true);
    $stockOpen = ['stock.manager', 'stock.alert', 'stock.issued', 'stock.conversion', 'stock.transfers'];

    $purchaseOpen = in_array($active, [
        'purchases.create', 'purchases.index', 'purchases.returns', 'purchases.debit-notes',
    ], true);

    $salesOpen = in_array($active, [
        'sales.pos', 'sales.index', 'sales.returns', 'sales.voids', 'sales.credit-notes', 'sales.orders',
        'quotations.index',
    ], true);

    $supplierOpen = in_array($active, [
        'suppliers.create', 'suppliers.index',
    ], true);

    $customerOpen = in_array($active, [
        'customers.categories', 'customers.create', 'customers.index', 'customers.archived',
    ], true);

    $expenseOpen = in_array($active, [
        'expenses.create', 'expenses.index',
    ], true);

    $accountingOpen = strpos((string) $active, 'accounting') === 0;
    $documentsOpen = strpos((string) $active, 'documents') === 0;
    $manufacturingOpen = strpos((string) $active, 'manufacturing') === 0;
    $hrOpen = strpos((string) $active, 'hr') === 0;
    $reportsOpen = strpos((string) $active, 'reports') === 0;
    $usersOpen = in_array($active, ['users.index', 'users.create', 'users.logs', 'roles.index'], true)
        || strpos((string) $active, 'users') === 0
        || strpos((string) $active, 'roles') === 0;
    $settingsOpen = strpos((string) $active, 'settings') === 0 || $active === 'billing.index';

    $menu = [
        [
            'key' => 'dashboard',
            'label' => 'Dashboard',
            'icon' => 'fa-dashboard',
            'url' => route('dashboard'),
            'show' => true,
        ],
        [
            'key' => 'products',
            'label' => 'Items/Products',
            'icon' => 'fa-cubes',
            'url' => '#',
            'show' => $can('products.view') || $can('inventory.view'),
            'open' => $productOpen,
            'children' => [
                ['key' => 'products.create', 'label' => 'New Item', 'icon' => 'fa-plus', 'url' => route('products.create'), 'show' => $can('products.create')],
                ['key' => 'products.index', 'label' => 'Items List', 'icon' => 'fa-list', 'url' => route('products.index'), 'show' => $can('products.view')],
                ['key' => 'stock.manager', 'label' => 'Stock Manager', 'icon' => 'fa-th-list', 'url' => route('stock.manager'), 'show' => $can('inventory.view')],
                ['key' => 'stock.transfers', 'label' => 'Stock Transfers', 'icon' => 'fa-exchange', 'url' => route('stock.transfers.index'), 'show' => $can('inventory.transfer') || $can('inventory.view')],
                ['key' => 'categories.index', 'label' => 'Categories List', 'icon' => 'fa-list-ul', 'url' => route('categories.index'), 'show' => $can('categories.view')],
                ['key' => 'brands.index', 'label' => 'Brands List', 'icon' => 'fa-list-ul', 'url' => route('brands.index'), 'show' => $can('brands.view')],
                ['key' => 'units.index', 'label' => 'Unit List(UOM)', 'icon' => 'fa-list-ul', 'url' => route('units.index'), 'show' => $can('units.view')],
                ['key' => 'products.labels', 'label' => 'Print Labels', 'icon' => 'fa-barcode', 'url' => route('products.labels'), 'show' => $can('products.view')],
                ['key' => 'stock.issued', 'label' => 'Issued/Damaged', 'icon' => 'fa-map-marker', 'url' => route('stock.issued'), 'show' => $can('inventory.adjust')],
                ['key' => 'products.prices', 'label' => 'Price Change Log', 'icon' => 'fa-info-circle', 'url' => route('products.prices'), 'show' => $can('products.view')],
                ['key' => 'stock.conversion', 'label' => 'Stock Conversion', 'icon' => 'fa-exchange', 'url' => route('stock.conversion'), 'show' => $can('inventory.adjust')],
                ['key' => 'stock.alert', 'label' => 'Stock Alert', 'icon' => 'fa-battery-half', 'url' => route('stock.alert'), 'show' => $can('inventory.view')],
            ],
        ],
        [
            'key' => 'purchases',
            'label' => 'Purchase',
            'icon' => 'fa-list-alt',
            'url' => '#',
            'show' => $can('purchases.view'),
            'open' => $purchaseOpen,
            'children' => [
                ['key' => 'purchases.create', 'label' => 'New Purchase', 'icon' => 'fa-plus', 'url' => route('purchases.create'), 'show' => $can('purchases.create')],
                ['key' => 'purchases.index', 'label' => 'Purchase List', 'icon' => 'fa-list', 'url' => route('purchases.index'), 'show' => $can('purchases.view')],
                ['key' => 'purchases.returns', 'label' => 'Purchase Return', 'icon' => 'fa-refresh', 'url' => route('purchase-returns.index'), 'show' => $can('purchases.return')],
                ['key' => 'purchases.debit-notes', 'label' => 'Debit Notes', 'icon' => 'fa-list', 'url' => route('debit-notes.index'), 'show' => $can('purchases.view')],
            ],
        ],
        [
            'key' => 'sales',
            'label' => 'Sales',
            'icon' => 'fa-shopping-cart',
            'url' => '#',
            'show' => $can('sales.view') || $can('pos.view'),
            'open' => $salesOpen,
            'children' => [
                ['key' => 'sales.pos', 'label' => 'POS', 'icon' => 'fa-shopping-cart', 'url' => route('pos.index'), 'show' => $can('pos.view')],
                ['key' => 'sales.index', 'label' => 'Sales List', 'icon' => 'fa-list', 'url' => route('sales.index'), 'show' => $can('sales.view')],
                ['key' => 'sales.returns', 'label' => 'Sales Return', 'icon' => 'fa-refresh', 'url' => route('sales.returns'), 'show' => $can('sales.return')],
                ['key' => 'sales.voids', 'label' => 'Cancelled Sales (Voids)', 'icon' => 'fa-times-circle', 'url' => route('sales.voids'), 'show' => $can('sales.void') || $can('pos.void')],
                ['key' => 'sales.credit-notes', 'label' => 'Credit Notes', 'icon' => 'fa-list', 'url' => route('sales.credit-notes'), 'show' => $can('sales.view') || $can('sales.credit_note') || $can('sales.return')],
                ['key' => 'quotations.index', 'label' => 'Quotations', 'icon' => 'fa-file-text-o', 'url' => route('quotations.index'), 'show' => $can('quotations.view')],
                ['key' => 'sales.orders', 'label' => 'Order Screen', 'icon' => 'fa-desktop', 'url' => route('sales.orders'), 'show' => $can('sales.create') || $can('pos.operate') || $can('pos.view')],
            ],
        ],
        [
            'key' => 'suppliers',
            'label' => 'Suppliers',
            'icon' => 'fa-truck',
            'url' => '#',
            'show' => $can('suppliers.view') || $can('suppliers.create'),
            'open' => $supplierOpen,
            'children' => [
                ['key' => 'suppliers.create', 'label' => 'New Supplier', 'icon' => 'fa-user-plus', 'url' => route('suppliers.create'), 'show' => $can('suppliers.create')],
                ['key' => 'suppliers.index', 'label' => 'Suppliers List', 'icon' => 'fa-list', 'url' => route('suppliers.index'), 'show' => $can('suppliers.view')],
            ],
        ],
        [
            'key' => 'customers',
            'label' => 'Customers',
            'icon' => 'fa-users',
            'url' => '#',
            'show' => $can('customers.view') || $can('customers.create'),
            'open' => $customerOpen,
            'children' => [
                ['key' => 'customers.categories', 'label' => 'Category', 'icon' => 'fa-tags', 'url' => route('customers.categories'), 'show' => $can('customers.view')],
                ['key' => 'customers.create', 'label' => 'New Customer', 'icon' => 'fa-user-plus', 'url' => route('customers.create'), 'show' => $can('customers.create')],
                ['key' => 'customers.index', 'label' => 'Customers List', 'icon' => 'fa-list', 'url' => route('customers.index'), 'show' => $can('customers.view')],
                ['key' => 'customers.archived', 'label' => 'Archived Customers', 'icon' => 'fa-archive', 'url' => route('customers.archived'), 'show' => $can('customers.view')],
            ],
        ],
        [
            'key' => 'expenses',
            'label' => 'Expenses',
            'icon' => 'fa-money',
            'url' => '#',
            'show' => $can('expenses.view') || $can('expenses.create'),
            'open' => $expenseOpen,
            'children' => [
                ['key' => 'expenses.create', 'label' => 'New Expense', 'icon' => 'fa-plus', 'url' => route('expenses.create'), 'show' => $can('expenses.create')],
                ['key' => 'expenses.index', 'label' => 'Expenses List', 'icon' => 'fa-list', 'url' => route('expenses.index'), 'show' => $can('expenses.view')],
            ],
        ],
        [
            'key' => 'accounting',
            'label' => 'Accounting',
            'icon' => 'fa-book',
            'url' => '#',
            'show' => $can('accounting.view') || $can('accounting.manage') || $can('payments.view'),
            'open' => $accountingOpen,
            'children' => [
                ['key' => 'accounting.types', 'label' => 'Accounts Type', 'icon' => 'fa-align-justify', 'url' => route('accounting.types'), 'show' => $can('accounting.view') || $can('accounting.manage')],
                ['key' => 'accounting.sub-types', 'label' => 'Sub-Accounts Type', 'icon' => 'fa-list', 'url' => route('accounting.sub-types'), 'show' => $can('accounting.view') || $can('accounting.manage')],
                ['key' => 'accounting.chart', 'label' => 'Chart of Accounts', 'icon' => 'fa-list-ul', 'url' => route('accounting.chart'), 'show' => $can('accounting.view') || $can('accounting.manage')],
                ['key' => 'accounting.balances', 'label' => 'Accounts Balances', 'icon' => 'fa-camera', 'url' => route('accounting.balances'), 'show' => $can('accounting.view') || $can('accounting.manage')],
                ['key' => 'accounting.money', 'label' => 'Money', 'icon' => 'fa-camera', 'url' => route('accounting.money'), 'show' => $can('accounting.view') || $can('accounting.manage') || $can('payments.view')],
                ['key' => 'accounting.journal', 'label' => 'Journal Entry', 'icon' => 'fa-file-text', 'url' => route('accounting.journal'), 'show' => $can('accounting.view') || $can('accounting.manage')],
                ['key' => 'accounting.profit-loss', 'label' => 'Profit & Loss', 'icon' => 'fa-balance-scale', 'url' => route('accounting.profit-loss'), 'show' => $can('accounting.view') || $can('accounting.manage')],
                ['key' => 'accounting.balance-sheet', 'label' => 'Balance Sheet', 'icon' => 'fa-balance-scale', 'url' => route('accounting.balance-sheet'), 'show' => $can('accounting.view') || $can('accounting.manage')],
                ['key' => 'accounting.trial-balance', 'label' => 'Trial Balance', 'icon' => 'fa-balance-scale', 'url' => route('accounting.trial-balance'), 'show' => $can('accounting.view') || $can('accounting.manage')],
                ['key' => 'accounting.combined-gl', 'label' => 'Combined GL', 'icon' => 'fa-balance-scale', 'url' => route('accounting.combined-gl'), 'show' => $can('accounting.view') || $can('accounting.manage')],
                ['key' => 'accounting.customers', 'label' => 'Customers Balances', 'icon' => 'fa-balance-scale', 'url' => route('accounting.customers'), 'show' => $can('accounting.view') || $can('accounting.manage')],
                ['key' => 'accounting.suppliers', 'label' => 'Suppliers Balances', 'icon' => 'fa-balance-scale', 'url' => route('accounting.suppliers'), 'show' => $can('accounting.view') || $can('accounting.manage')],
            ],
        ],
        [
            'key' => 'documents',
            'label' => 'Documents/Files',
            'icon' => 'fa-folder',
            'url' => '#',
            'show' => $can('documents.view') || $can('documents.manage'),
            'open' => $documentsOpen,
            'children' => [
                ['key' => 'documents.categories', 'label' => 'Files Category', 'icon' => 'fa-list', 'url' => route('documents.categories'), 'show' => $can('documents.view') || $can('documents.manage')],
                ['key' => 'documents.create', 'label' => 'New File', 'icon' => 'fa-plus-square', 'url' => route('documents.create'), 'show' => $can('documents.manage')],
                ['key' => 'documents.index', 'label' => 'Files List', 'icon' => 'fa-list', 'url' => route('documents.index'), 'show' => $can('documents.view') || $can('documents.manage')],
            ],
        ],
        [
            'key' => 'manufacturing',
            'label' => 'Manufacturing',
            'icon' => 'fa-industry',
            'url' => '#',
            'show' => ($can('products.view') || $can('inventory.view')) && subscription_allows('manufacturing'),
            'open' => $manufacturingOpen,
            'children' => [
                ['key' => 'manufacturing.bom.create', 'label' => 'Create BOM', 'icon' => 'fa-hand-o-right', 'url' => route('manufacturing.bom.create'), 'show' => $can('products.view') && subscription_allows('manufacturing')],
                ['key' => 'manufacturing.bom', 'label' => 'BOM list', 'icon' => 'fa-hand-o-right', 'url' => route('manufacturing.bom.index'), 'show' => $can('products.view') && subscription_allows('manufacturing')],
                ['key' => 'manufacturing.production', 'label' => 'Production list', 'icon' => 'fa-hand-o-right', 'url' => route('manufacturing.production.index'), 'show' => ($can('inventory.adjust') || $can('products.view')) && subscription_allows('manufacturing')],
                ['key' => 'manufacturing.packaging.setups', 'label' => 'Packaging Setups', 'icon' => 'fa-hand-o-right', 'url' => route('manufacturing.packaging.setups'), 'show' => $can('products.view') && subscription_allows('manufacturing')],
                ['key' => 'manufacturing.packaging', 'label' => 'Packaging Module', 'icon' => 'fa-hand-o-right', 'url' => route('manufacturing.packaging.index'), 'show' => $can('products.view') && subscription_allows('manufacturing')],
            ],
        ],
        [
            'key' => 'hr',
            'label' => 'Human Resource',
            'icon' => 'fa-globe',
            'url' => '#',
            'show' => $can('hr.view'),
            'open' => $hrOpen,
            'children' => [
                ['key' => 'hr.dashboard', 'label' => 'Dashboard', 'icon' => 'fa-dashboard', 'url' => route('hr.dashboard'), 'show' => $can('users.view')],
                [
                    'key' => 'hr.departments',
                    'label' => 'Departments',
                    'icon' => 'fa-list',
                    'url' => '#',
                    'show' => $can('users.view'),
                    'open' => strpos((string) $active, 'hr.departments') === 0,
                    'children' => [
                        ['key' => 'hr.departments.create', 'label' => 'Add Department', 'icon' => 'fa-plus-square', 'url' => route('hr.departments.create'), 'show' => $can('users.view')],
                        ['key' => 'hr.departments.index', 'label' => 'Departments List', 'icon' => 'fa-list', 'url' => route('hr.departments.index'), 'show' => $can('users.view')],
                    ],
                ],
                [
                    'key' => 'hr.designations',
                    'label' => 'Designation',
                    'icon' => 'fa-list',
                    'url' => '#',
                    'show' => $can('users.view'),
                    'open' => strpos((string) $active, 'hr.designations') === 0,
                    'children' => [
                        ['key' => 'hr.designations.create', 'label' => 'Add Designation', 'icon' => 'fa-plus-square', 'url' => route('hr.designations.create'), 'show' => $can('users.view')],
                        ['key' => 'hr.designations.index', 'label' => 'Designation List', 'icon' => 'fa-list', 'url' => route('hr.designations.index'), 'show' => $can('users.view')],
                    ],
                ],
                [
                    'key' => 'hr.employee-categories',
                    'label' => 'Employee Category',
                    'icon' => 'fa-list',
                    'url' => '#',
                    'show' => $can('users.view'),
                    'open' => strpos((string) $active, 'hr.employee-categories') === 0,
                    'children' => [
                        ['key' => 'hr.employee-categories.create', 'label' => 'Add Category', 'icon' => 'fa-plus-square', 'url' => route('hr.employee-categories.create'), 'show' => $can('users.view')],
                        ['key' => 'hr.employee-categories.index', 'label' => 'Category List', 'icon' => 'fa-list', 'url' => route('hr.employee-categories.index'), 'show' => $can('users.view')],
                    ],
                ],
                [
                    'key' => 'hr.employees',
                    'label' => 'Employees',
                    'icon' => 'fa-users',
                    'url' => '#',
                    'show' => $can('users.view'),
                    'open' => strpos((string) $active, 'hr.employees') === 0,
                    'children' => [
                        ['key' => 'hr.employees.create', 'label' => 'Register Employee', 'icon' => 'fa-plus-square', 'url' => route('hr.employees.create'), 'show' => $can('users.view')],
                        ['key' => 'hr.employees.index', 'label' => 'Employee List', 'icon' => 'fa-list', 'url' => route('hr.employees.index'), 'show' => $can('users.view')],
                        ['key' => 'hr.employees.archived', 'label' => 'Archived Employee List', 'icon' => 'fa-history', 'url' => route('hr.employees.archived'), 'show' => $can('users.view')],
                    ],
                ],
                [
                    'key' => 'hr.advance-salary',
                    'label' => 'Advance Salary',
                    'icon' => 'fa-money',
                    'url' => '#',
                    'show' => $can('users.view'),
                    'open' => strpos((string) $active, 'hr.advance-salary') === 0,
                    'children' => [
                        ['key' => 'hr.advance-salary.create', 'label' => 'Advance Salary', 'icon' => 'fa-plus-square', 'url' => route('hr.advance-salary.create'), 'show' => $can('users.view')],
                        ['key' => 'hr.advance-salary.index', 'label' => 'Advance Salary List', 'icon' => 'fa-list', 'url' => route('hr.advance-salary.index'), 'show' => $can('users.view')],
                    ],
                ],
                [
                    'key' => 'hr.allowances',
                    'label' => 'Allowances & Deductions',
                    'icon' => 'fa-list',
                    'url' => '#',
                    'show' => $can('users.view'),
                    'open' => strpos((string) $active, 'hr.allowances') === 0,
                    'children' => [
                        ['key' => 'hr.allowances.create', 'label' => 'Add New', 'icon' => 'fa-plus-square', 'url' => route('hr.allowances.create'), 'show' => $can('users.view')],
                        ['key' => 'hr.allowances.index', 'label' => 'View List', 'icon' => 'fa-list', 'url' => route('hr.allowances.index'), 'show' => $can('users.view')],
                        ['key' => 'hr.allowances.employee', 'label' => 'Employee Allow/Ded List', 'icon' => 'fa-list', 'url' => route('hr.allowances.employee'), 'show' => $can('users.view')],
                    ],
                ],
                [
                    'key' => 'hr.leave',
                    'label' => 'Manage Leave',
                    'icon' => 'fa-list',
                    'url' => '#',
                    'show' => $can('users.view'),
                    'open' => strpos((string) $active, 'hr.leave') === 0,
                    'children' => [
                        ['key' => 'hr.leave.holidays', 'label' => 'Holiday', 'icon' => 'fa-flag', 'url' => route('hr.leave.holidays'), 'show' => $can('users.view')],
                        ['key' => 'hr.leave.types.create', 'label' => 'Add Leave Types', 'icon' => 'fa-plus-square', 'url' => route('hr.leave.types.create'), 'show' => $can('users.view')],
                        ['key' => 'hr.leave.types', 'label' => 'Leave Types List', 'icon' => 'fa-list', 'url' => route('hr.leave.types'), 'show' => $can('users.view')],
                        ['key' => 'hr.leave.assign', 'label' => 'Assign Leaves', 'icon' => 'fa-plus-square', 'url' => route('hr.leave.assign'), 'show' => $can('users.view')],
                        ['key' => 'hr.leave.manage', 'label' => 'Manage Leaves', 'icon' => 'fa-list', 'url' => route('hr.leave.manage'), 'show' => $can('users.view')],
                    ],
                ],
                ['key' => 'hr.payroll', 'label' => 'Payroll', 'icon' => 'fa-list', 'url' => route('hr.payroll.index'), 'show' => $can('hr.payroll') || $can('hr.view') || $can('users.view')],
                ['key' => 'hr.attendance', 'label' => 'Time Attendance', 'icon' => 'fa-clock-o', 'url' => route('hr.attendance.index'), 'show' => $can('hr.attendance') || $can('hr.view') || $can('users.view')],
                ['key' => 'hr.payments', 'label' => 'Payments', 'icon' => 'fa-list', 'url' => route('hr.payments.index'), 'show' => $can('hr.payroll') || $can('hr.view') || $can('users.view')],
                ['key' => 'hr.reports', 'label' => 'HR Reports', 'icon' => 'fa-file-text-o', 'url' => route('hr.reports.index'), 'show' => $can('hr.view') || $can('users.view')],
            ],
        ],
        [
            'key' => 'reports',
            'label' => 'Reports Manager',
            'icon' => 'fa-bar-chart',
            'url' => '#',
            'show' => $can('reports.view'),
            'open' => $reportsOpen,
            'children' => [
                [
                    'key' => 'reports.summary',
                    'label' => 'Summary Reports',
                    'icon' => 'fa-list',
                    'url' => '#',
                    'show' => $can('reports.view'),
                    'open' => strpos((string) $active, 'reports.summary') === 0,
                    'children' => [
                        ['key' => 'reports.summary.daily', 'label' => 'Summary Daily Report', 'icon' => 'fa-copy', 'url' => route('reports.summary.daily'), 'show' => $can('reports.view')],
                        ['key' => 'reports.summary.employee-branch', 'label' => 'Employee Branch Report', 'icon' => 'fa-copy', 'url' => route('reports.summary.employee-branch'), 'show' => $can('reports.view')],
                        ['key' => 'reports.summary.branch', 'label' => 'Branch Report', 'icon' => 'fa-copy', 'url' => route('reports.summary.branch'), 'show' => $can('reports.view')],
                        ['key' => 'reports.summary.z', 'label' => 'Z-Report', 'icon' => 'fa-copy', 'url' => route('reports.summary.z'), 'show' => $can('reports.view')],
                        ['key' => 'reports.summary.debtors-creditors', 'label' => 'Debtors|Creditor', 'icon' => 'fa-copy', 'url' => route('reports.summary.debtors-creditors'), 'show' => $can('reports.view')],
                    ],
                ],
                [
                    'key' => 'reports.tax',
                    'label' => 'Tax Reports',
                    'icon' => 'fa-list',
                    'url' => '#',
                    'show' => $can('reports.tax') || $can('reports.view'),
                    'open' => strpos((string) $active, 'reports.tax') === 0,
                    'children' => [
                        ['key' => 'reports.tax.index', 'label' => 'Tax Report', 'icon' => 'fa-copy', 'url' => route('reports.tax.index'), 'show' => $can('reports.tax') || $can('reports.view')],
                        ['key' => 'reports.tax.vat', 'label' => 'Vat Report', 'icon' => 'fa-copy', 'url' => route('reports.tax.vat'), 'show' => $can('reports.tax') || $can('reports.view')],
                        ['key' => 'reports.tax.sales-vat', 'label' => 'VAT Report', 'icon' => 'fa-copy', 'url' => route('reports.tax.sales-vat'), 'show' => $can('reports.tax') || $can('reports.view')],
                        ['key' => 'reports.tax.monthly-vat', 'label' => 'Monthly VAT Report', 'icon' => 'fa-copy', 'url' => route('reports.tax.monthly-vat'), 'show' => $can('reports.tax') || $can('reports.view')],
                    ],
                ],
                [
                    'key' => 'reports.sales',
                    'label' => 'Sales Reports',
                    'icon' => 'fa-list',
                    'url' => '#',
                    'show' => $can('reports.sales') || $can('reports.view'),
                    'open' => strpos((string) $active, 'reports.sales') === 0,
                    'children' => [
                        ['key' => 'reports.sales.employee-clearance', 'label' => 'Employee Clearance', 'icon' => 'fa-copy', 'url' => route('reports.sales.employee-clearance'), 'show' => $can('reports.sales') || $can('reports.view')],
                        ['key' => 'reports.sales.cashier-clearance', 'label' => 'Cashier Clearance', 'icon' => 'fa-copy', 'url' => route('reports.sales.cashier-clearance'), 'show' => $can('reports.sales') || $can('reports.view')],
                        ['key' => 'reports.sales.sales', 'label' => 'Sales Report', 'icon' => 'fa-copy', 'url' => route('reports.sales.sales'), 'show' => $can('reports.sales') || $can('reports.view')],
                        ['key' => 'reports.sales.sales-custom', 'label' => 'Sales Report Custom', 'icon' => 'fa-copy', 'url' => route('reports.sales.sales-custom'), 'show' => $can('reports.sales') || $can('reports.view')],
                        ['key' => 'reports.sales.sales-employees', 'label' => 'Sales Report(Employees)', 'icon' => 'fa-copy', 'url' => route('reports.sales.sales-employees'), 'show' => $can('reports.sales') || $can('reports.view')],
                        ['key' => 'reports.sales.sales-summary', 'label' => 'Sales Report-Summary', 'icon' => 'fa-copy', 'url' => route('reports.sales.sales-summary'), 'show' => $can('reports.sales') || $can('reports.view')],
                        ['key' => 'reports.sales.item-sales', 'label' => 'Item Sales Report', 'icon' => 'fa-copy', 'url' => route('reports.sales.item-sales'), 'show' => $can('reports.sales') || $can('reports.view')],
                        ['key' => 'reports.sales.items-category-summary', 'label' => 'Items Category-Summary', 'icon' => 'fa-copy', 'url' => route('reports.sales.items-category-summary'), 'show' => $can('reports.sales') || $can('reports.view')],
                        ['key' => 'reports.sales.item-sales-summary', 'label' => 'Item Sales Report-Summary', 'icon' => 'fa-copy', 'url' => route('reports.sales.item-sales-summary'), 'show' => $can('reports.sales') || $can('reports.view')],
                        ['key' => 'reports.sales.payments', 'label' => 'Sales Payments Report', 'icon' => 'fa-copy', 'url' => route('reports.sales.payments'), 'show' => $can('reports.sales') || $can('reports.view')],
                        ['key' => 'reports.sales.commission', 'label' => 'Sales Commission', 'icon' => 'fa-copy', 'url' => route('reports.sales.commission'), 'show' => $can('reports.sales') || $can('reports.view')],
                        ['key' => 'reports.sales.returns', 'label' => 'Sales Return Report', 'icon' => 'fa-copy', 'url' => route('reports.sales.returns'), 'show' => $can('reports.sales') || $can('reports.view')],
                        ['key' => 'reports.sales.cancelled', 'label' => 'Sales Cancel Report', 'icon' => 'fa-copy', 'url' => route('reports.sales.cancelled'), 'show' => $can('reports.sales') || $can('reports.view')],
                        ['key' => 'reports.sales.complementary', 'label' => 'Complementary Sales', 'icon' => 'fa-copy', 'url' => route('reports.sales.complementary'), 'show' => $can('reports.sales') || $can('reports.view')],
                        ['key' => 'reports.sales.credit-aging', 'label' => 'Credit Sales Aging', 'icon' => 'fa-copy', 'url' => route('reports.sales.credit-aging'), 'show' => $can('reports.sales') || $can('reports.view')],
                    ],
                ],
                [
                    'key' => 'reports.purchases',
                    'label' => 'Purchase Reports',
                    'icon' => 'fa-list',
                    'url' => '#',
                    'show' => $can('reports.purchases') || $can('reports.view'),
                    'open' => strpos((string) $active, 'reports.purchases') === 0,
                    'children' => [
                        ['key' => 'reports.purchases.purchase', 'label' => 'Purchase Report', 'icon' => 'fa-copy', 'url' => route('reports.purchases.purchase'), 'show' => $can('reports.purchases') || $can('reports.view')],
                        ['key' => 'reports.purchases.items', 'label' => 'Items Purchase Report', 'icon' => 'fa-copy', 'url' => route('reports.purchases.items'), 'show' => $can('reports.purchases') || $can('reports.view')],
                        ['key' => 'reports.purchases.payments', 'label' => 'Purchase Payments Report', 'icon' => 'fa-copy', 'url' => route('reports.purchases.payments'), 'show' => $can('reports.purchases') || $can('reports.view')],
                    ],
                ],
                [
                    'key' => 'reports.stock',
                    'label' => 'Stock/Products Reports',
                    'icon' => 'fa-list',
                    'url' => '#',
                    'show' => $can('reports.stock') || $can('reports.view'),
                    'open' => strpos((string) $active, 'reports.stock') === 0,
                    'children' => [
                        ['key' => 'reports.stock.price-list', 'label' => 'Price List', 'icon' => 'fa-copy', 'url' => route('reports.stock.price-list'), 'show' => $can('reports.stock') || $can('reports.view')],
                        ['key' => 'reports.stock.stock', 'label' => 'Stock Report', 'icon' => 'fa-copy', 'url' => route('reports.stock.stock'), 'show' => $can('reports.stock') || $can('reports.view')],
                        ['key' => 'reports.stock.stock-as-at', 'label' => 'Stock Report as At', 'icon' => 'fa-copy', 'url' => route('reports.stock.stock-as-at'), 'show' => $can('reports.stock') || $can('reports.view')],
                        ['key' => 'reports.stock.stock-as-at-detailed', 'label' => 'Stock Report as At(Detailed)', 'icon' => 'fa-copy', 'url' => route('reports.stock.stock-as-at-detailed'), 'show' => $can('reports.stock') || $can('reports.view')],
                        ['key' => 'reports.stock.template', 'label' => 'Stock Template', 'icon' => 'fa-copy', 'url' => route('reports.stock.template'), 'show' => $can('reports.stock') || $can('reports.view')],
                        ['key' => 'reports.stock.ledger', 'label' => 'Items Ledger Report', 'icon' => 'fa-copy', 'url' => route('reports.stock.ledger'), 'show' => $can('reports.stock') || $can('reports.view')],
                        ['key' => 'reports.stock.transfer', 'label' => 'Stock Transfer Report', 'icon' => 'fa-copy', 'url' => route('reports.stock.transfer'), 'show' => $can('reports.stock') || $can('reports.view')],
                        ['key' => 'reports.stock.adjust', 'label' => 'Stock Adjust Report', 'icon' => 'fa-copy', 'url' => route('reports.stock.adjust'), 'show' => $can('reports.stock') || $can('reports.view')],
                        ['key' => 'reports.stock.alert', 'label' => 'Stock Alert Report', 'icon' => 'fa-copy', 'url' => route('reports.stock.alert'), 'show' => $can('reports.stock') || $can('reports.view')],
                        ['key' => 'reports.stock.valuation', 'label' => 'Items Valuation Report', 'icon' => 'fa-copy', 'url' => route('reports.stock.valuation'), 'show' => $can('reports.stock') || $can('reports.view')],
                        ['key' => 'reports.stock.damaged', 'label' => 'Damaged Products Report', 'icon' => 'fa-copy', 'url' => route('reports.stock.damaged'), 'show' => $can('reports.stock') || $can('reports.view')],
                        ['key' => 'reports.stock.issued', 'label' => 'Issued Products Report', 'icon' => 'fa-copy', 'url' => route('reports.stock.issued'), 'show' => $can('reports.stock') || $can('reports.view')],
                        ['key' => 'reports.stock.consumption', 'label' => 'Items Consumption Report', 'icon' => 'fa-copy', 'url' => route('reports.stock.consumption'), 'show' => $can('reports.stock') || $can('reports.view')],
                        ['key' => 'reports.stock.production', 'label' => 'Production Report', 'icon' => 'fa-copy', 'url' => route('reports.stock.production'), 'show' => $can('reports.stock') || $can('reports.view')],
                    ],
                ],
                ['key' => 'reports.expenses', 'label' => 'Expense Report', 'icon' => 'fa-copy', 'url' => route('reports.expenses'), 'show' => $can('reports.expenses') || $can('reports.view')],
                ['key' => 'reports.suppliers', 'label' => 'Suppliers Report', 'icon' => 'fa-copy', 'url' => route('reports.suppliers'), 'show' => $can('reports.view')],
                ['key' => 'reports.loyalty', 'label' => 'Loyalty Points Report', 'icon' => 'fa-copy', 'url' => route('reports.loyalty'), 'show' => $can('reports.view')],
                ['key' => 'reports.expired', 'label' => 'Expired Items Report', 'icon' => 'fa-copy', 'url' => route('reports.expired'), 'show' => $can('reports.stock') || $can('reports.view')],
                [
                    'key' => 'reports.customers',
                    'label' => 'Customer Reports',
                    'icon' => 'fa-list',
                    'url' => '#',
                    'show' => $can('reports.customers') || $can('reports.view'),
                    'open' => strpos((string) $active, 'reports.customers') === 0,
                    'children' => [
                        ['key' => 'reports.customers.customers', 'label' => 'Customers Report', 'icon' => 'fa-copy', 'url' => route('reports.customers.customers'), 'show' => $can('reports.customers') || $can('reports.view')],
                        ['key' => 'reports.customers.statement', 'label' => 'Customers Statement(PDF)', 'icon' => 'fa-copy', 'url' => route('reports.customers.statement'), 'show' => $can('reports.customers') || $can('reports.view')],
                        ['key' => 'reports.customers.purchases', 'label' => 'Customer Purchase Report', 'icon' => 'fa-copy', 'url' => route('reports.customers.purchases'), 'show' => $can('reports.customers') || $can('reports.view')],
                    ],
                ],
                ['key' => 'reports.user-logs', 'label' => 'User Logs', 'icon' => 'fa-copy', 'url' => route('reports.user-logs'), 'show' => $can('reports.audit') || $can('reports.view')],
                ['key' => 'reports.audit', 'label' => 'Audit Trail Report', 'icon' => 'fa-copy', 'url' => route('reports.audit'), 'show' => $can('reports.audit') || $can('reports.view')],
            ],
        ],
        [
            'key' => 'users',
            'label' => 'Users Management',
            'icon' => 'fa-user',
            'url' => '#',
            'show' => $can('users.view') || $can('roles.view'),
            'open' => $usersOpen,
            'children' => [
                ['key' => 'roles.index', 'label' => 'Roles List', 'icon' => 'fa-list', 'url' => route('roles.index'), 'show' => $can('roles.view') || $can('roles.manage'), 'badge' => 'new'],
                ['key' => 'users.create', 'label' => 'New User', 'icon' => 'fa-plus-square', 'url' => route('users.create'), 'show' => $can('users.create')],
                ['key' => 'users.index', 'label' => 'Users List', 'icon' => 'fa-list', 'url' => route('users.index'), 'show' => $can('users.view')],
                ['key' => 'users.logs', 'label' => 'User Logs', 'icon' => 'fa-list', 'url' => route('users.logs'), 'show' => $can('users.view') || $can('reports.audit')],
            ],
        ],
        [
            'key' => 'settings',
            'label' => 'Settings',
            'icon' => 'fa-cog',
            'url' => '#',
            'show' => $can('settings.view'),
            'open' => $settingsOpen,
            'children' => [
                ['key' => 'settings.company', 'label' => 'Company Profile', 'icon' => 'fa-briefcase', 'url' => route('settings.company'), 'show' => $can('settings.view') || $can('settings.company')],
                ['key' => 'billing.index', 'label' => 'Billing', 'icon' => 'fa-credit-card', 'url' => route('billing.index'), 'show' => $user->isCompanyAdmin() || $can('settings.view') || $can('settings.company')],
                ['key' => 'settings.branches', 'label' => 'Manage Branch', 'icon' => 'fa-sitemap', 'url' => route('settings.branches'), 'show' => $can('settings.branches') || $can('branches.view') || $can('settings.view')],
                ['key' => 'settings.general', 'label' => 'Site Settings', 'icon' => 'fa-shield', 'url' => route('settings.general'), 'show' => $can('settings.view') || $can('settings.company')],
                ['key' => 'settings.tax', 'label' => 'Tax List', 'icon' => 'fa-percent', 'url' => route('settings.tax'), 'show' => $can('settings.view')],
                ['key' => 'settings.salutation', 'label' => 'Salutation', 'icon' => 'fa-list', 'url' => route('settings.lookups', 'salutations'), 'show' => $can('settings.view')],
                ['key' => 'settings.progress', 'label' => 'Progress Status', 'icon' => 'fa-list', 'url' => route('settings.lookups', 'progress-statuses'), 'show' => $can('settings.view')],
                [
                    'key' => 'settings.places',
                    'label' => 'Places',
                    'icon' => 'fa-paper-plane',
                    'url' => '#',
                    'show' => $can('settings.view'),
                    'open' => strpos((string) $active, 'settings.places') === 0,
                    'children' => [
                        ['key' => 'settings.places.counties', 'label' => 'Counties', 'icon' => 'fa-list', 'url' => route('settings.lookups', 'counties'), 'show' => $can('settings.view')],
                        ['key' => 'settings.places.cities', 'label' => 'Cities / Towns', 'icon' => 'fa-list', 'url' => route('settings.lookups', 'cities'), 'show' => $can('settings.view')],
                    ],
                ],
                ['key' => 'settings.currency', 'label' => 'Currency List', 'icon' => 'fa-link', 'url' => route('settings.lookups', 'currencies'), 'show' => $can('settings.view'), 'badge' => 'new'],
                ['key' => 'settings.password', 'label' => 'Change Password', 'icon' => 'fa-lock', 'url' => route('settings.password'), 'show' => true],
                ['key' => 'settings.audit', 'label' => 'Audit Trail', 'icon' => 'fa-shield', 'url' => route('settings.audit'), 'show' => $can('reports.audit') || $can('reports.view') || $can('settings.view')],
                ['key' => 'settings.backup', 'label' => 'Database Backup', 'icon' => 'fa-database', 'url' => route('settings.backup'), 'show' => $can('settings.view')],
            ],
        ],
        [
            'key' => 'logout',
            'label' => 'Sign out',
            'icon' => 'fa-sign-out',
            'url' => route('logout'),
            'show' => true,
            'logout' => true,
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
        @foreach ($menu as $item)
            @if(!empty($item['show']))
                @php
                    $hasChildren = !empty($item['children']);
                    $isOpen = !empty($item['open']) || ($hasChildren && $active === $item['key']);
                    $isActive = $active === $item['key'] || !empty($item['open']);
                @endphp
                <li class="{{ $isActive ? 'active' : '' }} {{ $hasChildren ? 'has-submenu' : '' }} {{ $isOpen ? 'open' : '' }} {{ !empty($item['logout']) ? 'sx-signout' : '' }}">
                    @if(!empty($item['logout']))
                        <form action="{{ $item['url'] }}" method="post">
                            @csrf
                            <button type="submit">
                                <i class="fa {{ $item['icon'] }}"></i>
                                <span class="menu-label">{{ $item['label'] }}</span>
                            </button>
                        </form>
                    @else
                    <a href="{{ $item['url'] }}" class="{{ $hasChildren ? 'fleet-submenu-toggle' : '' }}">
                        <i class="fa {{ $item['icon'] }}"></i>
                        <span class="menu-label">{{ $item['label'] }}</span>
                        @if($hasChildren)
                            <i class="fa fa-angle-down menu-caret"></i>
                        @endif
                    </a>
                    @endif
                    @if($hasChildren)
                        <ul class="fleet-submenu">
                            @foreach ($item['children'] as $child)
                                @if(!isset($child['show']) || $child['show'])
                                    @php
                                        $childKey = $child['key'] ?? '';
                                        $hasGrandChildren = !empty($child['children']);
                                        $childOpen = !empty($child['open']) || ($hasGrandChildren && collect($child['children'])->contains(function ($g) use ($active) {
                                            return ($g['key'] ?? '') === $active;
                                        }));
                                        $childActive = $active === $childKey || $childOpen;
                                    @endphp
                                    <li class="{{ $childActive ? 'active' : '' }} {{ $hasGrandChildren ? 'has-submenu' : '' }} {{ $childOpen ? 'open' : '' }}">
                                        <a href="{{ $child['url'] }}" class="{{ $hasGrandChildren ? 'fleet-submenu-toggle' : '' }}">
                                            @if(!empty($child['icon']))
                                                <i class="fa {{ $child['icon'] }}"></i>
                                            @endif
                                            {{ $child['label'] }}
                                            @if(!empty($child['badge']))
                                                <span class="label label-success" style="margin-left:6px;font-size:10px;padding:2px 6px;">{{ $child['badge'] }}</span>
                                            @endif
                                            @if($hasGrandChildren)
                                                <i class="fa fa-angle-left menu-caret"></i>
                                            @endif
                                        </a>
                                        @if($hasGrandChildren)
                                            <ul class="fleet-submenu fleet-submenu-nested">
                                                @foreach ($child['children'] as $grand)
                                                    @if(!isset($grand['show']) || $grand['show'])
                                                        <li class="{{ $active === ($grand['key'] ?? '') ? 'active' : '' }}">
                                                            <a href="{{ $grand['url'] }}">
                                                                @if(!empty($grand['icon']))
                                                                    <i class="fa {{ $grand['icon'] }}"></i>
                                                                @endif
                                                                {{ $grand['label'] }}
                                                            </a>
                                                        </li>
                                                    @endif
                                                @endforeach
                                            </ul>
                                        @endif
                                    </li>
                                @endif
                            @endforeach
                        </ul>
                    @endif
                </li>
            @endif
        @endforeach
    </ul>
</aside>
