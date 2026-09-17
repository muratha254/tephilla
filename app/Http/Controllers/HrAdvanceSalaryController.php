<?php

namespace App\Http\Controllers;

use App\Models\HrAdvanceSalary;
use App\Models\HrEmployee;
use App\Models\LedgerAccount;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HrAdvanceSalaryController extends Controller
{
    public function index()
    {
        $this->authorizeView();

        return view('hr.advance-salaries.index', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'hr.advance-salary.index',
            'advances' => HrAdvanceSalary::query()
                ->with(['employee', 'ledgerAccount'])
                ->orderByDesc('advance_date')
                ->orderByDesc('id')
                ->get(),
            'canManage' => $this->canManage(),
        ]));
    }

    public function create()
    {
        $this->authorizeManage();

        return view('hr.advance-salaries.form', array_merge(fleet_shared_view_data(), $this->formLookups(), [
            'activeMenu' => 'hr.advance-salary.create',
            'advance' => new HrAdvanceSalary([
                'advance_date' => now()->toDateString(),
            ]),
            'canManage' => true,
        ]));
    }

    public function store(Request $request)
    {
        $this->authorizeManage();
        $companyId = (int) auth()->user()->company_id;
        $data = $this->validateAdvance($request, $companyId);
        $employee = HrEmployee::query()->findOrFail($data['employee_id']);

        HrAdvanceSalary::query()->create([
            'company_id' => $companyId,
            'branch_id' => $employee->branch_id ?: $this->currentBranchId(),
            'employee_id' => $employee->id,
            'ledger_account_id' => $data['ledger_account_id'],
            'user_id' => auth()->id(),
            'advance_date' => $data['advance_date'],
            'amount' => round((float) $data['amount'], 2),
            'description' => $data['description'] ?? null,
        ]);

        return redirect()->route('hr.advance-salary.index')->with('success', 'Advance salary allocated.');
    }

    public function edit(HrAdvanceSalary $advance)
    {
        $this->authorizeManage();

        return view('hr.advance-salaries.form', array_merge(fleet_shared_view_data(), $this->formLookups(), [
            'activeMenu' => 'hr.advance-salary.index',
            'advance' => $advance,
            'canManage' => true,
        ]));
    }

    public function update(Request $request, HrAdvanceSalary $advance)
    {
        $this->authorizeManage();
        $companyId = (int) auth()->user()->company_id;
        $data = $this->validateAdvance($request, $companyId);
        $employee = HrEmployee::query()->findOrFail($data['employee_id']);

        $advance->update([
            'branch_id' => $employee->branch_id ?: $this->currentBranchId(),
            'employee_id' => $employee->id,
            'ledger_account_id' => $data['ledger_account_id'],
            'advance_date' => $data['advance_date'],
            'amount' => round((float) $data['amount'], 2),
            'description' => $data['description'] ?? null,
        ]);

        return redirect()->route('hr.advance-salary.index')->with('success', 'Advance salary updated.');
    }

    public function destroy(HrAdvanceSalary $advance)
    {
        $this->authorizeManage();
        $advance->delete();

        return redirect()->route('hr.advance-salary.index')->with('success', 'Advance salary deleted.');
    }

    private function validateAdvance(Request $request, int $companyId): array
    {
        return $request->validate([
            'employee_id' => ['required', Rule::exists('hr_employees', 'id')->where('company_id', $companyId)],
            'ledger_account_id' => ['required', Rule::exists('ledger_accounts', 'id')->where('company_id', $companyId)],
            'advance_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:2000',
        ]);
    }

    private function formLookups(): array
    {
        return [
            'employees' => HrEmployee::query()->where('status', 'active')->orderBy('name')->get(),
            'accounts' => LedgerAccount::query()->where('is_active', true)->orderBy('name')->get(),
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
