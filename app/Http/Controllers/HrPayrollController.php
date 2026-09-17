<?php

namespace App\Http\Controllers;

use App\Models\HrEmployee;
use App\Models\HrEmployeeCharge;
use App\Models\HrPayrollItem;
use App\Models\HrPayrollRun;
use App\Services\DocumentNumberService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class HrPayrollController extends Controller
{
    public function index()
    {
        $this->authorizeView();

        return view('hr.payroll.index', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'hr.payroll',
            'runs' => HrPayrollRun::query()->with('user')->withCount('items')->orderByDesc('id')->get(),
            'canManage' => $this->canManage(),
        ]));
    }

    public function create()
    {
        $this->authorizeManage();

        $start = now()->startOfMonth();
        $end = now()->endOfMonth();

        return view('hr.payroll.create', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'hr.payroll',
            'employees' => HrEmployee::query()->where('status', 'active')->orderBy('name')->get(),
            'period_label' => $start->format('F Y'),
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
            'selectedBranchId' => $this->currentBranchId(),
            'canManage' => true,
        ]));
    }

    public function store(Request $request, DocumentNumberService $numbers)
    {
        $this->authorizeManage();
        $companyId = (int) auth()->user()->company_id;

        $data = $request->validate([
            'period_label' => 'required|string|max:100',
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('company_id', $companyId)],
            'notes' => 'nullable|string|max:2000',
            'employee_ids' => 'required|array|min:1',
            'employee_ids.*' => ['integer', Rule::exists('hr_employees', 'id')->where('company_id', $companyId)],
        ]);

        $employees = HrEmployee::query()
            ->whereIn('id', $data['employee_ids'])
            ->where('status', 'active')
            ->get();

        if ($employees->isEmpty()) {
            return back()->withInput()->with('error', 'Select at least one active employee.');
        }

        $run = DB::transaction(function () use ($data, $numbers, $companyId, $employees) {
            $run = HrPayrollRun::query()->create([
                'company_id' => $companyId,
                'branch_id' => $data['branch_id'] ?? $this->currentBranchId(),
                'user_id' => auth()->id(),
                'number' => $numbers->next($companyId, 'hr_payroll'),
                'period_label' => $data['period_label'],
                'period_start' => $data['period_start'],
                'period_end' => $data['period_end'],
                'status' => HrPayrollRun::STATUS_DRAFT,
                'notes' => $data['notes'] ?? null,
            ]);

            $totalGross = 0;
            $totalDeductions = 0;
            $totalNet = 0;

            foreach ($employees as $employee) {
                $calc = $this->calculateEmployeePay($employee, $data['period_start'], $data['period_end'], $data['period_label']);
                HrPayrollItem::query()->create([
                    'company_id' => $companyId,
                    'payroll_run_id' => $run->id,
                    'employee_id' => $employee->id,
                    'basic_salary' => $calc['basic_salary'],
                    'allowances' => $calc['allowances'],
                    'deductions' => $calc['deductions'],
                    'gross_pay' => $calc['gross_pay'],
                    'net_pay' => $calc['net_pay'],
                    'breakdown_json' => $calc['breakdown'],
                ]);
                $totalGross += $calc['gross_pay'];
                $totalDeductions += $calc['deductions'];
                $totalNet += $calc['net_pay'];
            }

            $run->update([
                'total_gross' => round($totalGross, 2),
                'total_deductions' => round($totalDeductions, 2),
                'total_net' => round($totalNet, 2),
            ]);

            return $run;
        });

        return redirect()->route('hr.payroll.show', $run)->with('success', 'Payroll run generated.');
    }

    public function show(HrPayrollRun $payroll)
    {
        $this->authorizeView();
        $payroll->load(['items.employee', 'user', 'approvedBy', 'branch']);

        return view('hr.payroll.show', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'hr.payroll',
            'payroll' => $payroll,
            'canManage' => $this->canManage(),
        ]));
    }

    public function process(HrPayrollRun $payroll)
    {
        $this->authorizeManage();

        if ($payroll->status !== HrPayrollRun::STATUS_DRAFT) {
            return back()->with('error', 'Only draft payroll can be processed.');
        }

        $payroll->update([
            'status' => HrPayrollRun::STATUS_PROCESSED,
            'processed_at' => now(),
        ]);

        return redirect()->route('hr.payroll.show', $payroll)->with('success', 'Payroll processed.');
    }

    public function approve(HrPayrollRun $payroll)
    {
        $this->authorizeManage();

        if (! in_array($payroll->status, [HrPayrollRun::STATUS_DRAFT, HrPayrollRun::STATUS_PROCESSED], true)) {
            return back()->with('error', 'Payroll cannot be approved in its current status.');
        }

        $payroll->update([
            'status' => HrPayrollRun::STATUS_APPROVED,
            'processed_at' => $payroll->processed_at ?: now(),
            'approved_at' => now(),
            'approved_by' => auth()->id(),
        ]);

        return redirect()->route('hr.payroll.show', $payroll)->with('success', 'Payroll approved.');
    }

    public function destroy(HrPayrollRun $payroll)
    {
        $this->authorizeManage();

        if ($payroll->status !== HrPayrollRun::STATUS_DRAFT) {
            return back()->with('error', 'Only draft payroll can be deleted.');
        }

        $payroll->delete();

        return redirect()->route('hr.payroll.index')->with('success', 'Payroll deleted.');
    }

    public function payslip(HrPayrollItem $item)
    {
        $this->authorizeView();
        $item->load(['employee.department', 'employee.designation', 'payrollRun']);

        return view('hr.payroll.payslip', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'hr.payroll',
            'item' => $item,
            'print' => request()->boolean('print'),
        ]));
    }

    /**
     * @return array{basic_salary:float,allowances:float,deductions:float,gross_pay:float,net_pay:float,breakdown:array}
     */
    private function calculateEmployeePay(HrEmployee $employee, string $periodStart, string $periodEnd, string $periodLabel): array
    {
        $basic = round((float) $employee->basic_salary, 2);
        $start = Carbon::parse($periodStart)->toDateString();
        $end = Carbon::parse($periodEnd)->toDateString();
        $monthLabel = Carbon::parse($periodStart)->format('F Y');

        $charges = HrEmployeeCharge::query()
            ->with('charge')
            ->where('employee_id', $employee->id)
            ->where('status', 'active')
            ->where(function ($query) use ($start, $end, $periodLabel, $monthLabel) {
                $query->whereBetween('recorded_date', [$start, $end])
                    ->orWhere('month_year', $periodLabel)
                    ->orWhere('month_year', $monthLabel);
            })
            ->get();

        $allowanceLines = [];
        $deductionLines = [];
        $allowances = 0.0;
        $deductions = 0.0;

        foreach ($charges as $row) {
            $amount = round((float) $row->amount, 2);
            $category = optional($row->charge)->category;
            $name = optional($row->charge)->name ?: 'Charge';
            if ($category === 'Allowance') {
                $allowances += $amount;
                $allowanceLines[] = ['name' => $name, 'amount' => $amount];
            } elseif ($category === 'Deduction') {
                $deductions += $amount;
                $deductionLines[] = ['name' => $name, 'amount' => $amount];
            }
        }

        $allowances = round($allowances, 2);
        $deductions = round($deductions, 2);
        $gross = round($basic + $allowances, 2);
        $net = round($gross - $deductions, 2);

        return [
            'basic_salary' => $basic,
            'allowances' => $allowances,
            'deductions' => $deductions,
            'gross_pay' => $gross,
            'net_pay' => $net,
            'breakdown' => [
                'allowances' => $allowanceLines,
                'deductions' => $deductionLines,
            ],
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
