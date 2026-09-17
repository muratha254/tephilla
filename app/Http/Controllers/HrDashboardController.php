<?php

namespace App\Http\Controllers;

use App\Models\HrDepartment;
use App\Models\HrDesignation;
use App\Models\HrEmployee;
use App\Models\HrEmployeeCategory;
use Illuminate\Support\Facades\Schema;

class HrDashboardController extends Controller
{
    public function index()
    {
        abort_unless(($u = auth()->user()) && ($u->hasPermission('hr.view') || $u->hasPermission('users.view')), 403);

        $symbol = optional(auth()->user()->company)->currency_symbol
            ?: optional(auth()->user()->company)->currency_code
            ?: 'Ksh';

        $activeEmployees = 0;
        $inactiveEmployees = 0;
        $paidSalaries = 0.0;
        $pendingSalaries = 0.0;

        if (Schema::hasTable('hr_employees')) {
            $activeEmployees = HrEmployee::query()->where('status', 'active')->count();
            $inactiveEmployees = HrEmployee::query()->where('status', '!=', 'active')->count();
        }

        $departmentsCount = HrDepartment::query()->count();
        $designationsCount = Schema::hasTable('hr_designations') ? HrDesignation::query()->count() : 0;
        $categoriesCount = Schema::hasTable('hr_employee_categories') ? HrEmployeeCategory::query()->count() : 0;

        $departmentTree = HrDepartment::query()
            ->with(['designations' => function ($q) {
                $q->orderBy('name');
            }])
            ->orderBy('name')
            ->get()
            ->map(function (HrDepartment $department) {
                return [
                    'name' => $department->name,
                    'designations' => $department->designations->map(function (HrDesignation $designation) {
                        $count = Schema::hasTable('hr_employees')
                            ? HrEmployee::query()->where('designation_id', $designation->id)->count()
                            : 0;

                        return [
                            'name' => $designation->name,
                            'employees' => $count,
                        ];
                    })->values()->all(),
                ];
            })->values()->all();

        return view('hr.dashboard', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'hr.dashboard',
            'currencySymbol' => $symbol,
            'stats' => [
                'activeEmployees' => $activeEmployees,
                'inactiveEmployees' => $inactiveEmployees,
                'departments' => $departmentsCount,
                'designations' => $designationsCount,
                'categories' => $categoriesCount,
                'paidSalaries' => $paidSalaries,
                'pendingSalaries' => $pendingSalaries,
            ],
            'departmentTree' => $departmentTree,
        ]));
    }
}
