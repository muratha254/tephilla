<?php

namespace App\Http\Controllers;

use App\Models\HrEmployee;
use App\Models\HrPayrollItem;
use App\Models\HrPayrollRun;
use App\Models\HrSalaryPayment;
use App\Services\DocumentNumberService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class HrSalaryPaymentController extends Controller
{
    public function index()
    {
        $this->authorizeView();

        return view('hr.payments.index', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'hr.payments',
            'payments' => HrSalaryPayment::query()
                ->with(['employee', 'payrollRun', 'user', 'branch'])
                ->orderByDesc('id')
                ->get(),
            'canManage' => $this->canManage(),
        ]));
    }

    public function create(Request $request)
    {
        $this->authorizeManage();

        $payrollItem = null;
        if ($request->filled('payroll_item_id')) {
            $payrollItem = HrPayrollItem::query()->with(['employee', 'payrollRun'])->find($request->input('payroll_item_id'));
        }

        return view('hr.payments.create', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'hr.payments',
            'employees' => HrEmployee::query()->where('status', 'active')->orderBy('name')->get(),
            'payrollItems' => HrPayrollItem::query()
                ->with(['employee', 'payrollRun'])
                ->whereHas('payrollRun', function ($query) {
                    $query->whereIn('status', [HrPayrollRun::STATUS_PROCESSED, HrPayrollRun::STATUS_APPROVED]);
                })
                ->orderByDesc('id')
                ->limit(200)
                ->get(),
            'methods' => config('sellix.payment_methods', []),
            'selectedBranchId' => $this->currentBranchId(),
            'payrollItem' => $payrollItem,
            'canManage' => true,
        ]));
    }

    public function store(Request $request, DocumentNumberService $numbers)
    {
        $this->authorizeManage();
        $companyId = (int) auth()->user()->company_id;

        $data = $request->validate([
            'employee_id' => ['required', Rule::exists('hr_employees', 'id')->where('company_id', $companyId)],
            'payroll_item_id' => ['nullable', Rule::exists('hr_payroll_items', 'id')->where('company_id', $companyId)],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('company_id', $companyId)],
            'payment_date' => 'required|date',
            'method' => 'required|string|max:32',
            'amount' => 'required|numeric|min:0.01',
            'reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:2000',
        ]);

        $employee = HrEmployee::query()->findOrFail($data['employee_id']);
        $payrollItem = null;
        $payrollRunId = null;

        if (! empty($data['payroll_item_id'])) {
            $payrollItem = HrPayrollItem::query()->with('payrollRun')->findOrFail($data['payroll_item_id']);
            if ((int) $payrollItem->employee_id !== (int) $employee->id) {
                return back()->withInput()->with('error', 'Payroll item does not belong to the selected employee.');
            }
            $payrollRunId = $payrollItem->payroll_run_id;
        }

        $payment = DB::transaction(function () use ($data, $numbers, $companyId, $employee, $payrollItem, $payrollRunId) {
            return HrSalaryPayment::query()->create([
                'company_id' => $companyId,
                'branch_id' => $data['branch_id'] ?? $employee->branch_id ?: $this->currentBranchId(),
                'employee_id' => $employee->id,
                'payroll_run_id' => $payrollRunId,
                'payroll_item_id' => $payrollItem ? $payrollItem->id : null,
                'user_id' => auth()->id(),
                'number' => $numbers->next($companyId, 'hr_payment'),
                'payment_date' => $data['payment_date'],
                'method' => $data['method'],
                'amount' => round((float) $data['amount'], 2),
                'reference' => $data['reference'] ?? null,
                'status' => HrSalaryPayment::STATUS_PAID,
                'notes' => $data['notes'] ?? null,
            ]);
        });

        return redirect()->route('hr.payments.show', $payment)->with('success', 'Salary payment recorded.');
    }

    public function show(HrSalaryPayment $payment)
    {
        $this->authorizeView();
        $payment->load(['employee', 'payrollRun', 'payrollItem', 'user', 'branch']);

        return view('hr.payments.show', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'hr.payments',
            'payment' => $payment,
            'canManage' => $this->canManage(),
        ]));
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
