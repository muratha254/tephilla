<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\PermissionCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TestUsersSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->first();
        if (! $company) {
            $this->command?->error('No company found. Run SellixCoreSeeder first.');

            return;
        }

        app()->instance('currentCompanyId', $company->id);

        $branch = Branch::query()
            ->where('company_id', $company->id)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();

        if (! $branch) {
            $this->command?->error('No branch found for company #' . $company->id);

            return;
        }

        foreach (PermissionCatalog::permissions() as $name => $meta) {
            Permission::query()->updateOrCreate(
                ['name' => $name],
                [
                    'display_name' => $meta['label'],
                    'module' => $meta['module'],
                ]
            );
        }

        $permissionIds = Permission::query()->pluck('id', 'name');
        $roles = [];

        foreach (PermissionCatalog::roles() as $name => $meta) {
            $role = Role::query()->updateOrCreate(
                [
                    'company_id' => $company->id,
                    'name' => $name,
                ],
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

        $users = [
            [
                'role' => PermissionCatalog::SUPER_ADMIN,
                'name' => 'Test Super Admin',
                'email' => 'test.superadmin@newpos.local',
                'username' => 'test.superadmin',
                'password' => 'Sp3r@dm1n!2026',
            ],
            [
                'role' => PermissionCatalog::COMPANY_ADMIN,
                'name' => 'Test Company Admin',
                'email' => 'test.admin@newpos.local',
                'username' => 'test.admin',
                'password' => 'C0mp@dmin!2026',
            ],
            [
                'role' => PermissionCatalog::BRANCH_MANAGER,
                'name' => 'Test Branch Manager',
                'email' => 'test.manager@newpos.local',
                'username' => 'test.manager',
                'password' => 'M@nager!2026Br',
            ],
            [
                'role' => PermissionCatalog::CASHIER,
                'name' => 'Test Cashier',
                'email' => 'test.cashier@newpos.local',
                'username' => 'test.cashier',
                'password' => 'C@shier!2026Pos',
            ],
            [
                'role' => PermissionCatalog::ACCOUNTANT,
                'name' => 'Test Accountant',
                'email' => 'test.accountant@newpos.local',
                'username' => 'test.accountant',
                'password' => 'Acc0unt!2026Gl',
            ],
            [
                'role' => PermissionCatalog::INVENTORY_MANAGER,
                'name' => 'Test Inventory',
                'email' => 'test.inventory@newpos.local',
                'username' => 'test.inventory',
                'password' => 'Inv3nt0ry!2026',
            ],
            [
                'role' => PermissionCatalog::SALESPERSON,
                'name' => 'Test Salesperson',
                'email' => 'test.sales@newpos.local',
                'username' => 'test.sales',
                'password' => 'S@les!2026Rep',
            ],
            [
                'role' => PermissionCatalog::HR_OFFICER,
                'name' => 'Test HR Officer',
                'email' => 'test.hr@newpos.local',
                'username' => 'test.hr',
                'password' => 'Hr0ffic3r!2026',
            ],
        ];

        foreach ($users as $data) {
            $role = $roles[$data['role']] ?? null;
            if (! $role) {
                continue;
            }

            User::query()->updateOrCreate(
                ['email' => $data['email']],
                [
                    'company_id' => $company->id,
                    'branch_id' => $branch->id,
                    'role_id' => $role->id,
                    'name' => $data['name'],
                    'username' => $data['username'],
                    'password' => Hash::make($data['password']),
                    'is_active' => true,
                ]
            );
        }

        $this->command?->info('Test users synced for company: ' . $company->name);
    }
}
