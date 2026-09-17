<?php

namespace App\Http\Controllers;

use App\Models\HrCharge;
use App\Models\HrEmployee;
use App\Models\HrEmployeeCharge;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HrAllowanceDeductionController extends Controller
{
    public function chargesIndex()
    {
        $this->authorizeView();

        return view('hr.allowances.charges-index', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'hr.allowances.index',
            'charges' => HrCharge::query()->orderByDesc('id')->get(),
            'canManage' => $this->canManage(),
        ]));
    }

    public function chargesCreate()
    {
        $this->authorizeManage();

        return view('hr.allowances.charge-form', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'hr.allowances.create',
            'charge' => new HrCharge(['is_active' => true]),
            'canManage' => true,
        ]));
    }

    public function chargesStore(Request $request)
    {
        $this->authorizeManage();
        $data = $this->validateCharge($request);

        HrCharge::query()->create([
            'company_id' => (int) auth()->user()->company_id,
            'category' => $data['category'],
            'taxable' => $data['taxable'],
            'name' => $data['name'],
            'is_active' => true,
        ]);

        return redirect()->route('hr.allowances.index')->with('success', 'Charge saved.');
    }

    public function chargesEdit(HrCharge $charge)
    {
        $this->authorizeManage();

        return view('hr.allowances.charge-form', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'hr.allowances.index',
            'charge' => $charge,
            'canManage' => true,
        ]));
    }

    public function chargesUpdate(Request $request, HrCharge $charge)
    {
        $this->authorizeManage();
        $data = $this->validateCharge($request);

        $charge->update([
            'category' => $data['category'],
            'taxable' => $data['taxable'],
            'name' => $data['name'],
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $charge->is_active,
        ]);

        return redirect()->route('hr.allowances.index')->with('success', 'Charge updated.');
    }

    public function chargesDestroy(HrCharge $charge)
    {
        $this->authorizeManage();
        $charge->delete();

        return redirect()->route('hr.allowances.index')->with('success', 'Charge deleted.');
    }

    public function employeeChargesIndex()
    {
        $this->authorizeView();

        return view('hr.allowances.employee-charges-index', array_merge(fleet_shared_view_data(), $this->employeeFormLookups(), [
            'activeMenu' => 'hr.allowances.employee',
            'records' => HrEmployeeCharge::query()
                ->with(['employee', 'charge', 'user', 'branch'])
                ->orderByDesc('id')
                ->get(),
            'canManage' => $this->canManage(),
        ]));
    }

    public function employeeChargesCreate()
    {
        return redirect()->route('hr.allowances.employee');
    }

    public function employeeChargesStore(Request $request)
    {
        $this->authorizeManage();
        $companyId = (int) auth()->user()->company_id;
        $data = $this->validateEmployeeCharge($request, $companyId);
        $employee = HrEmployee::query()->findOrFail($data['employee_id']);
        $recordedDate = $data['recorded_date'];

        HrEmployeeCharge::query()->create([
            'company_id' => $companyId,
            'branch_id' => $employee->branch_id ?: $this->currentBranchId(),
            'employee_id' => $employee->id,
            'charge_id' => $data['charge_id'],
            'user_id' => auth()->id(),
            'amount' => round((float) $data['amount'], 2),
            'month_year' => $data['month_year'] ?? \Carbon\Carbon::parse($recordedDate)->format('F Y'),
            'recorded_date' => $recordedDate,
            'status' => 'active',
            'notes' => $data['notes'] ?? null,
        ]);

        return redirect()->route('hr.allowances.employee')->with('success', 'Employee charge recorded.');
    }

    public function employeeChargesEdit(HrEmployeeCharge $record)
    {
        return redirect()->route('hr.allowances.employee', ['edit' => $record->id]);
    }

    public function employeeChargesUpdate(Request $request, HrEmployeeCharge $record)
    {
        $this->authorizeManage();
        $companyId = (int) auth()->user()->company_id;
        $data = $this->validateEmployeeCharge($request, $companyId);
        $employee = HrEmployee::query()->findOrFail($data['employee_id']);
        $recordedDate = $data['recorded_date'];

        $record->update([
            'branch_id' => $employee->branch_id ?: $this->currentBranchId(),
            'employee_id' => $employee->id,
            'charge_id' => $data['charge_id'],
            'amount' => round((float) $data['amount'], 2),
            'month_year' => $data['month_year'] ?? \Carbon\Carbon::parse($recordedDate)->format('F Y'),
            'recorded_date' => $recordedDate,
            'notes' => $data['notes'] ?? null,
        ]);

        return redirect()->route('hr.allowances.employee')->with('success', 'Employee charge updated.');
    }

    public function employeeChargesDestroy(HrEmployeeCharge $record)
    {
        $this->authorizeManage();
        $record->delete();

        return redirect()->route('hr.allowances.employee')->with('success', 'Employee charge deleted.');
    }

    private function validateCharge(Request $request): array
    {
        return $request->validate([
            'category' => 'required|in:Allowance,Deduction',
            'taxable' => 'required|in:Taxable,Non Taxable',
            'name' => 'required|string|max:255',
            'is_active' => 'nullable|boolean',
        ]);
    }

    private function validateEmployeeCharge(Request $request, int $companyId): array
    {
        return $request->validate([
            'employee_id' => ['required', Rule::exists('hr_employees', 'id')->where('company_id', $companyId)],
            'charge_id' => ['required', Rule::exists('hr_charges', 'id')->where('company_id', $companyId)],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('company_id', $companyId)],
            'amount' => 'required|numeric|min:0',
            'month_year' => 'nullable|string|max:50',
            'recorded_date' => 'required|date',
            'status' => 'nullable|in:active,inactive',
            'notes' => 'nullable|string|max:2000',
        ]);
    }

    private function employeeFormLookups(): array
    {
        return [
            'employees' => HrEmployee::query()->where('status', 'active')->orderBy('name')->get(),
            'charges' => HrCharge::query()->where('is_active', true)->orderBy('name')->get(),
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
