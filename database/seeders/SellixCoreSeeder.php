<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Customer;
use App\Models\ExpenseCategory;
use App\Models\NumberSequence;
use App\Models\Permission;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Tax;
use App\Models\Unit;
use App\Models\User;
use App\Support\PermissionCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SellixCoreSeeder extends Seeder
{
    public function run()
    {
        $company = Company::query()->create([
            'name' => 'TEPHILLA SYSTEM',
            'slug' => 'sellix-pos',
            'address' => '',
            'city' => '',
            'country' => 'Kenya',
            'phone' => '',
            'email' => '',
            'website' => '',
            'tax_pin' => '',
            'vat_number' => '',
            'currency_code' => config('sellix.currency_code', 'KES'),
            'currency_symbol' => config('sellix.currency_symbol', 'KSh'),
            'date_format' => config('sellix.date_format', 'd/m/Y'),
            'timezone' => config('sellix.timezone', 'Africa/Nairobi'),
            'is_active' => true,
        ]);

        app()->instance('currentCompanyId', $company->id);

        $branch = Branch::query()->create([
            'company_id' => $company->id,
            'name' => 'MAIN',
            'code' => 'MAIN',
            'is_default' => true,
            'is_active' => true,
        ]);

        foreach (PermissionCatalog::permissions() as $name => $meta) {
            Permission::query()->create([
                'name' => $name,
                'display_name' => $meta['label'],
                'module' => $meta['module'],
            ]);
        }

        $permissionIds = Permission::query()->pluck('id', 'name');
        $roles = [];

        foreach (PermissionCatalog::roles() as $name => $meta) {
            $role = Role::query()->create([
                'company_id' => $company->id,
                'name' => $name,
                'display_name' => $meta['label'],
                'description' => $meta['description'],
                'is_system' => true,
            ]);

            $ids = [];
            foreach ($meta['permissions'] as $permissionName) {
                if (isset($permissionIds[$permissionName])) {
                    $ids[] = $permissionIds[$permissionName];
                }
            }
            $role->permissions()->sync($ids);
            $roles[$name] = $role;
        }

        $admin = User::query()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'role_id' => $roles[PermissionCatalog::SUPER_ADMIN]->id,
            'name' => 'Admin',
            'email' => 'admin@mail.com',
            'username' => 'admin',
            'password' => Hash::make('1234'),
            'is_active' => true,
        ]);

        User::query()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'role_id' => $roles[PermissionCatalog::CASHIER]->id,
            'name' => 'Cashier',
            'email' => 'cashier@mail.com',
            'username' => 'cashier',
            'password' => Hash::make('1234'),
            'is_active' => true,
        ]);

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
            Setting::query()->create([
                'company_id' => $company->id,
                'group' => $group,
                'key' => $key,
                'value' => $value,
            ]);
        }

        foreach (config('sellix.document_prefixes') as $type => $prefix) {
            NumberSequence::query()->create([
                'company_id' => $company->id,
                'document_type' => $type,
                'prefix' => $prefix,
                'next_number' => 1,
                'padding' => (int) config('sellix.number_padding', 5),
            ]);
        }

        Tax::query()->create([
            'company_id' => $company->id,
            'name' => config('sellix.default_tax_name', 'VAT'),
            'rate' => config('sellix.default_tax_rate', 16),
            'is_inclusive' => true,
            'is_default' => true,
            'is_active' => true,
        ]);

        Unit::query()->create([
            'company_id' => $company->id,
            'name' => 'Piece',
            'short_name' => 'PCS',
            'multiplier' => 1,
            'is_active' => true,
        ]);

        Unit::query()->create([
            'company_id' => $company->id,
            'name' => 'Kilogram',
            'short_name' => 'KG',
            'multiplier' => 1,
            'is_active' => true,
        ]);

        foreach (['Black', 'Coffee Brown', 'Maroon Red', 'Black Red'] as $colourName) {
            \App\Models\Colour::query()->create([
                'company_id' => $company->id,
                'name' => $colourName,
                'is_active' => true,
            ]);
        }

        ProductCategory::query()->create([
            'company_id' => $company->id,
            'name' => 'General',
            'show_on_pos' => true,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        Customer::query()->create([
            'company_id' => $company->id,
            'type' => Customer::TYPE_WALK_IN,
            'name' => 'Walk-in Customer',
            'is_walk_in' => true,
            'is_active' => true,
        ]);

        foreach (['Rent', 'Utilities', 'Transport', 'Salaries', 'Office', 'Other'] as $name) {
            ExpenseCategory::query()->create([
                'company_id' => $company->id,
                'name' => $name,
                'is_active' => true,
            ]);
        }

        $this->command->info('TEPHILLA SYSTEM seeded. Admin: admin@mail.com / 1234');
        $this->command->info('Cashier: cashier@mail.com / 1234');

        return $admin;
    }
}
