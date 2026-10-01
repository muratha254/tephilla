<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Customer;
use App\Models\ExpenseCategory;
use App\Models\NumberSequence;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Tax;
use App\Models\Unit;
use App\Models\User;
use App\Support\PermissionCatalog;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CompanyProvisioner
{
    public function __construct(protected AccountingCatalog $accounting)
    {
    }

    /**
     * @return array{company: Company, branch: Branch, admin: User}
     */
    public function provision(array $companyData, array $adminData): array
    {
        $slug = $companyData['slug'] ?? Str::slug($companyData['name'] ?? 'business');
        if ($slug === '') {
            $slug = 'biz-' . Str::lower(Str::random(6));
        }
        $base = $slug;
        $i = 1;
        while (Company::query()->withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i;
            $i++;
        }

        $company = Company::query()->create([
            'name' => $companyData['name'],
            'owner_name' => $companyData['owner_name'] ?? ($adminData['name'] ?? null),
            'slug' => $slug,
            'address' => $companyData['address'] ?? '',
            'city' => $companyData['city'] ?? '',
            'country' => $companyData['country'] ?? 'Kenya',
            'phone' => $companyData['phone'] ?? '',
            'email' => $companyData['email'] ?? ($adminData['email'] ?? ''),
            'website' => $companyData['website'] ?? '',
            'tax_pin' => $companyData['tax_pin'] ?? '',
            'vat_number' => $companyData['vat_number'] ?? '',
            'currency_code' => $companyData['currency_code'] ?? config('sellix.currency_code', 'KES'),
            'currency_symbol' => $companyData['currency_symbol'] ?? config('sellix.currency_symbol', 'KSh'),
            'date_format' => $companyData['date_format'] ?? config('sellix.date_format', 'd/m/Y'),
            'timezone' => $companyData['timezone'] ?? config('sellix.timezone', 'Africa/Nairobi'),
            'is_active' => $companyData['is_active'] ?? true,
        ]);

        $previousCompany = app()->bound('currentCompanyId') ? app('currentCompanyId') : null;
        app()->instance('currentCompanyId', $company->id);

        try {
            $branch = Branch::query()->create([
                'company_id' => $company->id,
                'name' => $companyData['branch_name'] ?? 'MAIN',
                'code' => $companyData['branch_code'] ?? 'MAIN',
                'phone' => $companyData['phone'] ?? null,
                'email' => $companyData['email'] ?? null,
                'address' => $companyData['address'] ?? null,
                'is_default' => true,
                'is_active' => true,
            ]);

            $this->ensurePermissions();
            $roles = $this->seedRoles($company->id);
            $adminRole = $roles[PermissionCatalog::COMPANY_ADMIN] ?? $roles[PermissionCatalog::SUPER_ADMIN];

            $admin = User::query()->create([
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'role_id' => $adminRole->id,
                'name' => $adminData['name'],
                'email' => $adminData['email'],
                'username' => $adminData['username'],
                'password' => Hash::make($adminData['password']),
                'phone' => $adminData['phone'] ?? ($companyData['phone'] ?? null),
                'is_active' => true,
            ]);

            $this->seedDefaults($company, $branch);
            $this->accounting->ensure($company->id);
            $this->accounting->ensureOperationalAccounts($company->id);

            return compact('company', 'branch', 'admin');
        } finally {
            if ($previousCompany) {
                app()->instance('currentCompanyId', $previousCompany);
            }
        }
    }

    public function seedRoles(int $companyId): array
    {
        $permissionIds = Permission::query()->pluck('id', 'name');
        $roles = [];

        foreach (PermissionCatalog::roles() as $name => $meta) {
            $role = Role::query()->firstOrCreate(
                ['company_id' => $companyId, 'name' => $name],
                [
                    'display_name' => $meta['label'],
                    'description' => $meta['description'],
                    'is_system' => true,
                ]
            );

            $ids = [];
            foreach ($meta['permissions'] as $permissionName) {
                if (isset($permissionIds[$permissionName])) {
                    $ids[] = $permissionIds[$permissionName];
                }
            }
            $role->permissions()->sync($ids);
            $roles[$name] = $role;
        }

        return $roles;
    }

    public function syncCatalog(): void
    {
        $this->ensurePermissions();

        Company::query()->orderBy('id')->pluck('id')->each(function ($companyId) {
            $this->seedRoles((int) $companyId);
        });
    }

    protected function ensurePermissions(): void
    {
        foreach (PermissionCatalog::permissions() as $name => $meta) {
            Permission::query()->firstOrCreate(
                ['name' => $name],
                [
                    'display_name' => $meta['label'],
                    'module' => $meta['module'],
                ]
            );
        }
    }

    protected function seedDefaults(Company $company, Branch $branch): void
    {
        $settings = [
            'invoice_prefix' => 'INV-',
            'receipt_prefix' => 'RCP-',
            'quotation_prefix' => 'QT-',
            'purchase_order_prefix' => 'PO-',
            'receipt_paper_size' => '80mm',
            'tax_inclusive' => '1',
            'allow_pos_discount' => '1',
            'default_customer' => 'walk_in',
            'powered_by' => 'Powered by TEPHILLA SYSTEM',
            'powered_by_website' => '',
            'powered_by_email' => '',
        ];

        foreach ($settings as $key => $value) {
            $group = Str::startsWith($key, 'powered_by') ? 'company' : (Str::contains($key, ['prefix', 'paper']) ? 'documents' : 'pos');
            Setting::query()->firstOrCreate(
                ['company_id' => $company->id, 'key' => $key],
                ['group' => $group, 'value' => $value]
            );
        }

        foreach (config('sellix.document_prefixes', []) as $type => $prefix) {
            NumberSequence::query()->firstOrCreate(
                ['company_id' => $company->id, 'document_type' => $type],
                [
                    'prefix' => $prefix,
                    'next_number' => 1,
                    'padding' => (int) config('sellix.number_padding', 5),
                ]
            );
        }

        Tax::query()->firstOrCreate(
            ['company_id' => $company->id, 'is_default' => true],
            [
                'name' => config('sellix.default_tax_name', 'VAT'),
                'rate' => config('sellix.default_tax_rate', 16),
                'is_inclusive' => true,
                'is_active' => true,
            ]
        );

        Unit::query()->firstOrCreate(
            ['company_id' => $company->id, 'short_name' => 'PCS'],
            [
                'name' => 'Piece',
                'multiplier' => 1,
                'is_active' => true,
            ]
        );

        ProductCategory::query()->firstOrCreate(
            ['company_id' => $company->id, 'name' => 'General'],
            [
                'show_on_pos' => true,
                'sort_order' => 1,
                'is_active' => true,
            ]
        );

        $this->ensureRoofingCatalog($company);

        Customer::query()->firstOrCreate(
            ['company_id' => $company->id, 'is_walk_in' => true],
            [
                'type' => Customer::TYPE_WALK_IN,
                'name' => 'Walk-in Customer',
                'is_active' => true,
            ]
        );

        Unit::query()->firstOrCreate(
            ['company_id' => $company->id, 'short_name' => 'KG'],
            [
                'name' => 'Kilogram',
                'multiplier' => 1,
                'is_active' => true,
            ]
        );

        foreach (['Rent', 'Utilities', 'Transport', 'Salaries', 'Office', 'Other'] as $name) {
            ExpenseCategory::query()->firstOrCreate(
                ['company_id' => $company->id, 'name' => $name],
                ['is_active' => true]
            );
        }
    }

    public function ensureRoofingCatalogs(): void
    {
        Company::query()->orderBy('id')->each(function (Company $company) {
            $this->ensureRoofingCatalog($company);
        });
    }

    /**
     * Parent rows are categories. Child rows are product types.
     * Existing folding items keep their names and are grouped only when they have no category yet.
     */
    public function ensureRoofingCatalog(Company $company): void
    {
        $groups = [
            ['Roofing Sheets', 'Roofing Sheet', 10],
            ['Roofing Tiles', 'Roofing Tile', 20],
            ['Roofing Accessories', 'Roofing Accessory', 30],
            ['Roofing Materials / Components', 'Roofing Component', 40],
            ['Flat Sheets / Raw Materials', 'Flat Sheet', 50],
        ];

        $types = [];
        foreach ($groups as [$groupName, $typeName, $sort]) {
            $group = ProductCategory::query()->firstOrCreate(
                ['company_id' => $company->id, 'name' => $groupName],
                [
                    'show_on_pos' => true,
                    'sort_order' => $sort,
                    'is_active' => true,
                ]
            );
            $types[$typeName] = ProductCategory::query()->firstOrCreate(
                ['company_id' => $company->id, 'name' => $typeName],
                [
                    'parent_id' => $group->id,
                    'show_on_pos' => true,
                    'sort_order' => $sort,
                    'is_active' => true,
                ]
            );
        }

        $existing = [
            'Ridges' => 'Roofing Accessory',
            'Bardge Box' => 'Roofing Accessory',
            'Valleys' => 'Roofing Accessory',
            'Side Flash' => 'Roofing Accessory',
        ];

        foreach ($existing as $productName => $typeName) {
            Product::query()
                ->withoutGlobalScope('company')
                ->where('company_id', $company->id)
                ->whereNull('category_id')
                ->where('name', $productName)
                ->update(['category_id' => $types[$typeName]->id]);
        }

        $flatSheet = Product::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('name', 'Flat Sheet')
            ->orderBy('id')
            ->first();
        $legacySheet = Product::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('name', 'Flatsheets')
            ->orderBy('id')
            ->first();

        $sheet = $flatSheet ?: $legacySheet;
        if ($sheet) {
            if (! $flatSheet) {
                $sheet->name = 'Flat Sheet';
            }
            $sheet->category_id = $types['Flat Sheet']->id;
            $sheet->for_sale = false;
            $sheet->manage_stock = true;
            $sheet->save();
        }
    }
}
