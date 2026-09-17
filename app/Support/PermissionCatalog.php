<?php



namespace App\Support;



class PermissionCatalog

{

    public const SUPER_ADMIN = 'super_admin';

    public const COMPANY_ADMIN = 'company_admin';

    public const BRANCH_MANAGER = 'branch_manager';

    public const CASHIER = 'cashier';

    public const INVENTORY_MANAGER = 'inventory_manager';

    public const SALESPERSON = 'salesperson';

    public const ACCOUNTANT = 'accountant';

    public const HR_OFFICER = 'hr_officer';



    /**

     * @return array<string, array{module: string, label: string}>

     */

    public static function permissions(): array

    {

        return [

            'dashboard.view' => ['module' => 'dashboard', 'label' => 'View dashboard'],



            'pos.view' => ['module' => 'pos', 'label' => 'View POS'],

            'pos.operate' => ['module' => 'pos', 'label' => 'Operate POS'],

            'pos.hold' => ['module' => 'pos', 'label' => 'Hold POS invoices'],

            'pos.discount' => ['module' => 'pos', 'label' => 'Apply POS discounts'],

            'pos.void' => ['module' => 'pos', 'label' => 'Void POS sales'],

            'pos.credit_sale' => ['module' => 'pos', 'label' => 'Allow credit sales on POS'],



            'products.view' => ['module' => 'products', 'label' => 'View products'],

            'products.create' => ['module' => 'products', 'label' => 'Create products'],

            'products.update' => ['module' => 'products', 'label' => 'Update products'],

            'products.delete' => ['module' => 'products', 'label' => 'Delete products'],

            'products.view_cost' => ['module' => 'products', 'label' => 'View product cost prices'],



            'categories.view' => ['module' => 'products', 'label' => 'View categories'],

            'categories.manage' => ['module' => 'products', 'label' => 'Manage categories'],

            'brands.view' => ['module' => 'products', 'label' => 'View brands'],

            'brands.manage' => ['module' => 'products', 'label' => 'Manage brands'],

            'units.view' => ['module' => 'products', 'label' => 'View units'],

            'units.manage' => ['module' => 'products', 'label' => 'Manage units'],



            'inventory.view' => ['module' => 'inventory', 'label' => 'View inventory'],

            'inventory.adjust' => ['module' => 'inventory', 'label' => 'Adjust stock'],

            'inventory.transfer' => ['module' => 'inventory', 'label' => 'Transfer stock'],

            'inventory.allow_negative' => ['module' => 'inventory', 'label' => 'Allow negative stock'],



            'sales.view' => ['module' => 'sales', 'label' => 'View sales'],

            'sales.create' => ['module' => 'sales', 'label' => 'Create sales'],

            'sales.update' => ['module' => 'sales', 'label' => 'Update sales'],

            'sales.delete' => ['module' => 'sales', 'label' => 'Delete sales'],

            'sales.return' => ['module' => 'sales', 'label' => 'Process sale returns'],

            'sales.credit_note' => ['module' => 'sales', 'label' => 'Create credit notes'],

            'sales.void' => ['module' => 'sales', 'label' => 'Void sales'],

            'sales.print_invoice' => ['module' => 'sales', 'label' => 'Print invoices'],

            'sales.print_receipt' => ['module' => 'sales', 'label' => 'Print receipts'],

            'sales.delivery_note' => ['module' => 'sales', 'label' => 'Generate delivery notes'],



            'invoices.view' => ['module' => 'invoices', 'label' => 'View invoices'],

            'invoices.create' => ['module' => 'invoices', 'label' => 'Create invoices'],

            'invoices.update' => ['module' => 'invoices', 'label' => 'Update invoices'],



            'purchases.view' => ['module' => 'purchases', 'label' => 'View purchases'],

            'purchases.create' => ['module' => 'purchases', 'label' => 'Create purchase orders'],

            'purchases.update' => ['module' => 'purchases', 'label' => 'Update purchase orders'],

            'purchases.delete' => ['module' => 'purchases', 'label' => 'Delete purchase orders'],

            'purchases.receive' => ['module' => 'purchases', 'label' => 'Receive goods'],

            'purchases.return' => ['module' => 'purchases', 'label' => 'Return purchases'],



            'suppliers.view' => ['module' => 'suppliers', 'label' => 'View suppliers'],

            'suppliers.create' => ['module' => 'suppliers', 'label' => 'Create suppliers'],

            'suppliers.update' => ['module' => 'suppliers', 'label' => 'Update suppliers'],

            'suppliers.delete' => ['module' => 'suppliers', 'label' => 'Delete suppliers'],



            'customers.view' => ['module' => 'customers', 'label' => 'View customers'],

            'customers.create' => ['module' => 'customers', 'label' => 'Create customers'],

            'customers.update' => ['module' => 'customers', 'label' => 'Update customers'],

            'customers.delete' => ['module' => 'customers', 'label' => 'Delete customers'],



            'payments.view' => ['module' => 'payments', 'label' => 'View payments'],

            'payments.create' => ['module' => 'payments', 'label' => 'Create payments'],

            'payments.update' => ['module' => 'payments', 'label' => 'Update payments'],

            'payments.delete' => ['module' => 'payments', 'label' => 'Delete payments'],



            'quotations.view' => ['module' => 'quotations', 'label' => 'View quotations'],

            'quotations.create' => ['module' => 'quotations', 'label' => 'Create quotations'],

            'quotations.update' => ['module' => 'quotations', 'label' => 'Update quotations'],

            'quotations.delete' => ['module' => 'quotations', 'label' => 'Delete quotations'],

            'quotations.convert' => ['module' => 'quotations', 'label' => 'Convert quotations'],



            'expenses.view' => ['module' => 'expenses', 'label' => 'View expenses'],

            'expenses.create' => ['module' => 'expenses', 'label' => 'Create expenses'],

            'expenses.update' => ['module' => 'expenses', 'label' => 'Update expenses'],

            'expenses.delete' => ['module' => 'expenses', 'label' => 'Delete expenses'],



            'accounting.view' => ['module' => 'accounting', 'label' => 'View accounting'],

            'accounting.manage' => ['module' => 'accounting', 'label' => 'Manage accounting'],



            'documents.view' => ['module' => 'documents', 'label' => 'View documents'],

            'documents.manage' => ['module' => 'documents', 'label' => 'Manage documents'],



            'hr.view' => ['module' => 'hr', 'label' => 'View HR'],

            'hr.manage' => ['module' => 'hr', 'label' => 'Manage HR'],

            'hr.payroll' => ['module' => 'hr', 'label' => 'Manage payroll'],

            'hr.attendance' => ['module' => 'hr', 'label' => 'Manage attendance'],



            'reports.view' => ['module' => 'reports', 'label' => 'View reports'],

            'reports.sales' => ['module' => 'reports', 'label' => 'Sales reports'],

            'reports.stock' => ['module' => 'reports', 'label' => 'Stock reports'],

            'reports.purchases' => ['module' => 'reports', 'label' => 'Purchase reports'],

            'reports.customers' => ['module' => 'reports', 'label' => 'Customer reports'],

            'reports.expenses' => ['module' => 'reports', 'label' => 'Expense reports'],

            'reports.tax' => ['module' => 'reports', 'label' => 'Tax reports'],

            'reports.audit' => ['module' => 'reports', 'label' => 'Audit reports'],

            'reports.export' => ['module' => 'reports', 'label' => 'Export reports'],



            'users.view' => ['module' => 'users', 'label' => 'View users'],

            'users.create' => ['module' => 'users', 'label' => 'Create users'],

            'users.update' => ['module' => 'users', 'label' => 'Update users'],

            'users.delete' => ['module' => 'users', 'label' => 'Delete users'],

            'roles.view' => ['module' => 'users', 'label' => 'View roles'],

            'roles.manage' => ['module' => 'users', 'label' => 'Manage roles'],



            'settings.view' => ['module' => 'settings', 'label' => 'View settings'],

            'settings.company' => ['module' => 'settings', 'label' => 'Update company settings'],

            'settings.branches' => ['module' => 'settings', 'label' => 'Manage branches'],

            'settings.pos' => ['module' => 'settings', 'label' => 'Update POS settings'],

            'settings.print' => ['module' => 'settings', 'label' => 'Update print settings'],



            'branches.view' => ['module' => 'branches', 'label' => 'View branches'],

            'branches.manage' => ['module' => 'branches', 'label' => 'Manage branches'],

        ];

    }



    /**

     * @return array<string, array{label: string, description: string, permissions: array<int, string>|string}>

     */

    public static function roles(): array

    {

        $all = array_keys(self::permissions());



        return [

            self::SUPER_ADMIN => [

                'label' => 'Super Admin',

                'description' => 'Full access to the company and all modules.',

                'permissions' => $all,

            ],

            self::COMPANY_ADMIN => [

                'label' => 'Company Admin',

                'description' => 'Full access inside the company.',

                'permissions' => $all,

            ],

            self::BRANCH_MANAGER => [

                'label' => 'Branch Manager',

                'description' => 'Manages stock, sales, purchases and reports for a branch.',

                'permissions' => [

                    'dashboard.view',

                    'pos.view', 'pos.operate', 'pos.hold', 'pos.discount', 'pos.void', 'pos.credit_sale',

                    'products.view', 'products.create', 'products.update', 'products.view_cost',

                    'categories.view', 'categories.manage', 'brands.view', 'brands.manage', 'units.view', 'units.manage',

                    'inventory.view', 'inventory.adjust', 'inventory.transfer',

                    'sales.view', 'sales.create', 'sales.update', 'sales.return', 'sales.credit_note', 'sales.void',

                    'sales.print_invoice', 'sales.print_receipt', 'sales.delivery_note',

                    'invoices.view', 'invoices.create', 'invoices.update',

                    'purchases.view', 'purchases.create', 'purchases.update', 'purchases.receive', 'purchases.return',

                    'suppliers.view', 'suppliers.create', 'suppliers.update',

                    'customers.view', 'customers.create', 'customers.update',

                    'payments.view', 'payments.create',

                    'quotations.view', 'quotations.create', 'quotations.update', 'quotations.convert',

                    'expenses.view', 'expenses.create', 'expenses.update',

                    'accounting.view',

                    'documents.view', 'documents.manage',

                    'hr.view', 'hr.manage', 'hr.payroll', 'hr.attendance',

                    'reports.view', 'reports.sales', 'reports.stock', 'reports.purchases', 'reports.customers', 'reports.expenses', 'reports.tax',

                    'users.view',

                    'settings.view', 'settings.pos',

                    'branches.view',

                ],

            ],

            self::CASHIER => [

                'label' => 'Cashier',

                'description' => 'POS and own sales only.',

                'permissions' => [

                    'dashboard.view',

                    'pos.view', 'pos.operate', 'pos.hold',

                    'products.view',

                    'categories.view',

                    'sales.view', 'sales.print_receipt',

                    'customers.view', 'customers.create',

                    'payments.view', 'payments.create',

                ],

            ],

            self::INVENTORY_MANAGER => [

                'label' => 'Inventory Manager',

                'description' => 'Products, stock, receiving and adjustments.',

                'permissions' => [

                    'dashboard.view',

                    'products.view', 'products.create', 'products.update', 'products.view_cost',

                    'categories.view', 'categories.manage', 'brands.view', 'brands.manage', 'units.view', 'units.manage',

                    'inventory.view', 'inventory.adjust', 'inventory.transfer',

                    'purchases.view', 'purchases.create', 'purchases.update', 'purchases.receive', 'purchases.return',

                    'suppliers.view', 'suppliers.create', 'suppliers.update',

                    'reports.view', 'reports.stock', 'reports.purchases',

                ],

            ],

            self::SALESPERSON => [

                'label' => 'Salesperson',

                'description' => 'Quotations, customers and sales. No settings.',

                'permissions' => [

                    'dashboard.view',

                    'pos.view', 'pos.operate', 'pos.hold',

                    'products.view',

                    'sales.view', 'sales.create', 'sales.print_invoice', 'sales.print_receipt', 'sales.delivery_note',

                    'invoices.view', 'invoices.create',

                    'customers.view', 'customers.create', 'customers.update',

                    'quotations.view', 'quotations.create', 'quotations.update', 'quotations.convert',

                    'payments.view',

                    'reports.view', 'reports.sales', 'reports.customers',

                ],

            ],

            self::ACCOUNTANT => [

                'label' => 'Accountant',

                'description' => 'Payments, expenses, invoices and financial reports.',

                'permissions' => [

                    'dashboard.view',

                    'sales.view', 'sales.print_invoice', 'sales.print_receipt',

                    'invoices.view', 'invoices.create', 'invoices.update',

                    'purchases.view',

                    'suppliers.view',

                    'customers.view',

                    'payments.view', 'payments.create', 'payments.update',

                    'expenses.view', 'expenses.create', 'expenses.update',

                    'accounting.view', 'accounting.manage',

                    'documents.view',

                    'reports.view', 'reports.sales', 'reports.purchases', 'reports.customers', 'reports.expenses', 'reports.tax', 'reports.export',

                    'products.view', 'products.view_cost',

                ],

            ],

            self::HR_OFFICER => [

                'label' => 'HR Officer',

                'description' => 'Human resources, attendance, payroll and related reports.',

                'permissions' => [

                    'dashboard.view',

                    'users.view',

                    'hr.view', 'hr.manage', 'hr.payroll', 'hr.attendance',

                    'documents.view',

                ],

            ],

        ];

    }

}


