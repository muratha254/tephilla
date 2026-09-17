<?php

namespace App\Http\Controllers;

use App\Models\HrDepartment;
use App\Models\HrDesignation;
use App\Models\HrEmployee;
use App\Models\HrEmployeeCategory;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\Rule;

class HrEmployeeController extends Controller
{
    public function index()
    {
        $this->authorizeView();

        return view('hr.employees.index', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'hr.employees.index',
            'employees' => HrEmployee::query()
                ->with(['department', 'designation', 'category', 'branch'])
                ->orderByDesc('id')
                ->get(),
            'canManage' => $this->canManage(),
            'archived' => false,
        ]));
    }

    public function archived()
    {
        $this->authorizeView();

        return view('hr.employees.archived', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'hr.employees.archived',
            'employees' => HrEmployee::onlyTrashed()
                ->with(['department', 'designation', 'category', 'branch'])
                ->orderByDesc('id')
                ->get(),
            'canManage' => $this->canManage(),
        ]));
    }

    public function create()
    {
        $this->authorizeManage();

        return view('hr.employees.form', array_merge(fleet_shared_view_data(), $this->formLookups(), [
            'activeMenu' => 'hr.employees.create',
            'employee' => new HrEmployee([
                'branch_id' => $this->currentBranchId(),
                'payment_period' => 'Monthly',
                'status' => 'active',
                'apply_paye' => true,
                'apply_shif' => true,
                'apply_nssf' => true,
                'apply_housing_levy' => true,
                'joining_date' => now()->toDateString(),
            ]),
            'canManage' => true,
        ]));
    }

    public function store(Request $request)
    {
        $this->authorizeManage();
        $companyId = (int) auth()->user()->company_id;
        $data = $this->validateEmployee($request, $companyId);

        $employee = HrEmployee::query()->create($this->payload($data, $companyId));

        if (! $employee->employee_code) {
            $employee->update([
                'employee_code' => 'E' . str_pad((string) $employee->id, 4, '0', STR_PAD_LEFT),
            ]);
        }

        return redirect()->route('hr.employees.index')->with('success', 'Employee saved.');
    }

    public function edit(HrEmployee $employee)
    {
        $this->authorizeManage();

        return view('hr.employees.form', array_merge(fleet_shared_view_data(), $this->formLookups(), [
            'activeMenu' => 'hr.employees.index',
            'employee' => $employee,
            'canManage' => true,
        ]));
    }

    public function update(Request $request, HrEmployee $employee)
    {
        $this->authorizeManage();
        $companyId = (int) auth()->user()->company_id;
        $data = $this->validateEmployee($request, $companyId, $employee->id);

        $employee->update($this->payload($data, $companyId, $employee));

        return redirect()->route('hr.employees.index')->with('success', 'Employee updated.');
    }

    public function destroy(HrEmployee $employee)
    {
        $this->authorizeManage();
        $employee->delete();

        return redirect()->route('hr.employees.index')->with('success', 'Employee archived.');
    }

    public function restore($id)
    {
        $this->authorizeManage();
        $employee = HrEmployee::onlyTrashed()->findOrFail($id);
        $employee->restore();

        return redirect()->route('hr.employees.archived')->with('success', 'Employee restored.');
    }

    public function downloadTemplate()
    {
        $this->authorizeManage();
        $csv = "first_name,middle_name,last_name,joining_date,branch_id,department_id,designation_id,category_id,national_id,phone,email,basic_salary,gender,marital_status,county\n";
        $csv .= "John,,Doe,2026-01-15,,,, ,12345678,0700000000,john@example.com,50000,Male,Single,Nairobi\n";

        return Response::make($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="employee-upload-template.csv"',
        ]);
    }

    public function bulkUpload(Request $request)
    {
        $this->authorizeManage();
        $request->validate(['file' => 'required|file|mimes:csv,txt']);

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $header = fgetcsv($handle);
        $count = 0;
        $companyId = (int) auth()->user()->company_id;

        while (($row = fgetcsv($handle)) !== false) {
            if (count(array_filter($row)) === 0) {
                continue;
            }
            $data = array_combine($header, $row);
            if (! $data || empty($data['first_name']) || empty($data['last_name'])) {
                continue;
            }
            $employee = HrEmployee::query()->create([
                'company_id' => $companyId,
                'branch_id' => $data['branch_id'] ?: $this->currentBranchId(),
                'department_id' => $data['department_id'] ?: null,
                'designation_id' => $data['designation_id'] ?: null,
                'category_id' => $data['category_id'] ?: null,
                'first_name' => $data['first_name'],
                'middle_name' => $data['middle_name'] ?? null,
                'last_name' => $data['last_name'],
                'name' => HrEmployee::composeName($data['first_name'], $data['middle_name'] ?? null, $data['last_name']),
                'joining_date' => $data['joining_date'] ?? now()->toDateString(),
                'national_id' => $data['national_id'] ?? null,
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'basic_salary' => (float) ($data['basic_salary'] ?? 0),
                'gross_salary' => (float) ($data['basic_salary'] ?? 0),
                'gender' => $data['gender'] ?? null,
                'marital_status' => $data['marital_status'] ?? null,
                'county' => $data['county'] ?? null,
                'payment_period' => 'Monthly',
                'status' => 'active',
                'apply_paye' => true,
                'apply_shif' => true,
                'apply_nssf' => true,
                'apply_housing_levy' => true,
            ]);
            $employee->update([
                'employee_code' => 'E' . str_pad((string) $employee->id, 4, '0', STR_PAD_LEFT),
            ]);
            $count++;
        }
        fclose($handle);

        return redirect()->route('hr.employees.index')->with('success', "Imported {$count} employee(s).");
    }

    private function validateEmployee(Request $request, int $companyId, ?int $ignoreId = null): array
    {
        return $request->validate([
            'first_name' => 'required|string|max:100',
            'middle_name' => 'nullable|string|max:100',
            'last_name' => 'required|string|max:100',
            'joining_date' => 'required|date',
            'branch_id' => ['required', Rule::exists('branches', 'id')->where('company_id', $companyId)],
            'role_id' => ['nullable', Rule::exists('roles', 'id')->where('company_id', $companyId)],
            'department_id' => ['required', Rule::exists('hr_departments', 'id')->where('company_id', $companyId)],
            'designation_id' => ['nullable', Rule::exists('hr_designations', 'id')->where('company_id', $companyId)],
            'category_id' => ['required', Rule::exists('hr_employee_categories', 'id')->where('company_id', $companyId)],
            'national_id' => 'required|string|max:50',
            'phone' => 'required|string|max:30',
            'alt_phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:255',
            'kra_pin' => 'nullable|string|max:50',
            'shif_no' => 'nullable|string|max:50',
            'nssf_no' => 'nullable|string|max:50',
            'gender' => 'required|string|max:20',
            'marital_status' => 'required|string|max:30',
            'payment_period' => 'required|string|max:30',
            'basic_salary' => 'required|numeric|min:0',
            'advance_salary_limit' => 'nullable|numeric|min:0',
            'leave_counts' => 'nullable|integer|min:0',
            'county' => 'required|string|max:100',
            'postcode' => 'nullable|string|max:30',
            'home_address' => 'nullable|string|max:500',
            'payroll_number' => 'nullable|string|max:50',
            'bank_account_name' => 'nullable|string|max:255',
            'bank_account_number' => 'nullable|string|max:100',
            'bank_name' => 'nullable|string|max:255',
            'bank_branch' => 'nullable|string|max:255',
            'apply_paye' => 'required|boolean',
            'apply_shif' => 'required|boolean',
            'apply_nssf' => 'required|boolean',
            'apply_housing_levy' => 'required|boolean',
            'status' => 'nullable|in:active,inactive',
        ]);
    }

    private function payload(array $data, int $companyId, ?HrEmployee $employee = null): array
    {
        $name = HrEmployee::composeName($data['first_name'], $data['middle_name'] ?? null, $data['last_name']);
        $salary = round((float) $data['basic_salary'], 2);

        return [
            'company_id' => $companyId,
            'branch_id' => $data['branch_id'],
            'role_id' => $data['role_id'] ?? null,
            'department_id' => $data['department_id'],
            'designation_id' => $data['designation_id'] ?? null,
            'category_id' => $data['category_id'],
            'first_name' => $data['first_name'],
            'middle_name' => $data['middle_name'] ?? null,
            'last_name' => $data['last_name'],
            'name' => $name,
            'joining_date' => $data['joining_date'],
            'national_id' => $data['national_id'],
            'phone' => $data['phone'],
            'alt_phone' => $data['alt_phone'] ?? null,
            'email' => $data['email'] ?? null,
            'kra_pin' => $data['kra_pin'] ?? null,
            'shif_no' => $data['shif_no'] ?? null,
            'nssf_no' => $data['nssf_no'] ?? null,
            'gender' => $data['gender'],
            'marital_status' => $data['marital_status'],
            'payment_period' => $data['payment_period'],
            'basic_salary' => $salary,
            'gross_salary' => $salary,
            'advance_salary_limit' => round((float) ($data['advance_salary_limit'] ?? 0), 2),
            'leave_counts' => (int) ($data['leave_counts'] ?? 0),
            'county' => $data['county'],
            'postcode' => $data['postcode'] ?? null,
            'home_address' => $data['home_address'] ?? null,
            'payroll_number' => $data['payroll_number'] ?? null,
            'bank_account_name' => $data['bank_account_name'] ?? null,
            'bank_account_number' => $data['bank_account_number'] ?? null,
            'bank_name' => $data['bank_name'] ?? null,
            'bank_branch' => $data['bank_branch'] ?? null,
            'apply_paye' => (bool) $data['apply_paye'],
            'apply_shif' => (bool) $data['apply_shif'],
            'apply_nssf' => (bool) $data['apply_nssf'],
            'apply_housing_levy' => (bool) $data['apply_housing_levy'],
            'status' => $data['status'] ?? ($employee->status ?? 'active'),
            'employee_code' => $employee->employee_code ?? null,
        ];
    }

    private function formLookups(): array
    {
        return [
            'departments' => HrDepartment::query()->where('is_active', true)->orderBy('name')->get(),
            'designations' => HrDesignation::query()->where('is_active', true)->orderBy('name')->get(),
            'categories' => HrEmployeeCategory::query()->where('is_active', true)->orderBy('name')->get(),
            'roles' => Role::query()->orderBy('name')->get(),
            'counties' => config('sellix.kenya_counties', []),
            'selectedBranchId' => $this->currentBranchId(),
        ];
    }

    private function authorizeView(): void
    {
        $user = auth()->user();
        abort_unless($user && ($user->hasPermission('hr.view') || $user->hasPermission('users.view')), 403);
    }

    private function authorizeManage(): void
    {
        $user = auth()->user();
        abort_unless($user && (
            $user->hasPermission('hr.manage')
            || $user->hasPermission('hr.payroll')
            || $user->hasPermission('hr.attendance')
            || $user->hasPermission('users.create')
            || $user->hasPermission('users.update')
            || $user->hasPermission('users.view')
        ), 403);
    }

    private function canManage(): bool
    {
        $user = auth()->user();

        return $user && (
            $user->hasPermission('hr.manage')
            || $user->hasPermission('hr.payroll')
            || $user->hasPermission('hr.attendance')
            || $user->hasPermission('users.create')
            || $user->hasPermission('users.update')
            || $user->hasPermission('users.view')
        );
    }
}
