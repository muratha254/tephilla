<?php

namespace App\Http\Controllers;

use App\Models\HrAttendance;
use App\Models\HrEmployee;
use App\Models\HrLeave;
use App\Models\HrPayrollItem;
use App\Models\HrPayrollRun;
use App\Models\HrSalaryPayment;
use Illuminate\Http\Request;

class HrReportController extends Controller
{
    public function index()
    {
        $this->authorizeView();

        return view('hr.reports.index', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'hr.reports',
            'links' => [
                ['label' => 'Employees Report', 'route' => 'hr.reports.employees', 'icon' => 'fa-users'],
                ['label' => 'Attendance Report', 'route' => 'hr.reports.attendance', 'icon' => 'fa-clock-o'],
                ['label' => 'Leave Report', 'route' => 'hr.reports.leave', 'icon' => 'fa-calendar'],
                ['label' => 'Payroll Report', 'route' => 'hr.reports.payroll', 'icon' => 'fa-list-alt'],
                ['label' => 'Payroll Summary', 'route' => 'hr.reports.payroll-summary', 'icon' => 'fa-bar-chart'],
                ['label' => 'Payment History', 'route' => 'hr.reports.payment-history', 'icon' => 'fa-money'],
            ],
        ]));
    }

    public function employees(Request $request)
    {
        $this->authorizeView();

        $status = $request->input('status', 'active');
        $generated = $request->boolean('show');

        $rows = collect();
        if ($generated) {
            $rows = HrEmployee::query()
                ->with(['department', 'designation', 'category', 'branch'])
                ->when($status !== 'all', function ($query) use ($status) {
                    $query->where('status', $status);
                })
                ->orderBy('name')
                ->get();
        }

        return view('hr.reports.employees', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'hr.reports',
            'status' => $status,
            'generated' => $generated,
            'rows' => $rows,
        ]));
    }

    public function attendance(Request $request)
    {
        $this->authorizeView();

        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to = $request->input('to', now()->toDateString());
        $employeeId = $request->filled('employee_id') ? (int) $request->input('employee_id') : null;
        $generated = $request->boolean('show');

        $rows = collect();
        if ($generated) {
            $rows = HrAttendance::query()
                ->with(['employee', 'branch'])
                ->whereBetween('attendance_date', [$from, $to])
                ->when($employeeId, function ($query) use ($employeeId) {
                    $query->where('employee_id', $employeeId);
                })
                ->orderBy('attendance_date')
                ->orderBy('employee_id')
                ->get();
        }

        return view('hr.reports.attendance', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'hr.reports',
            'from' => $from,
            'to' => $to,
            'employeeId' => $employeeId,
            'generated' => $generated,
            'employees' => HrEmployee::query()->where('status', 'active')->orderBy('name')->get(),
            'rows' => $rows,
        ]));
    }

    public function leave(Request $request)
    {
        $this->authorizeView();

        $from = $request->input('from', now()->startOfYear()->toDateString());
        $to = $request->input('to', now()->toDateString());
        $employeeId = $request->filled('employee_id') ? (int) $request->input('employee_id') : null;
        $generated = $request->boolean('show');

        $rows = collect();
        if ($generated) {
            $rows = HrLeave::query()
                ->with(['employee', 'leaveType', 'branch'])
                ->where(function ($query) use ($from, $to) {
                    $query->whereBetween('start_date', [$from, $to])
                        ->orWhereBetween('end_date', [$from, $to]);
                })
                ->when($employeeId, function ($query) use ($employeeId) {
                    $query->where('employee_id', $employeeId);
                })
                ->orderByDesc('start_date')
                ->get();
        }

        return view('hr.reports.leave', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'hr.reports',
            'from' => $from,
            'to' => $to,
            'employeeId' => $employeeId,
            'generated' => $generated,
            'employees' => HrEmployee::query()->where('status', 'active')->orderBy('name')->get(),
            'rows' => $rows,
        ]));
    }

    public function payroll(Request $request)
    {
        $this->authorizeView();

        $from = $request->input('from', now()->startOfYear()->toDateString());
        $to = $request->input('to', now()->toDateString());
        $generated = $request->boolean('show');

        $rows = collect();
        if ($generated) {
            $rows = HrPayrollRun::query()
                ->withCount('items')
                ->whereBetween('period_start', [$from, $to])
                ->orderByDesc('period_start')
                ->get();
        }

        return view('hr.reports.payroll', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'hr.reports',
            'from' => $from,
            'to' => $to,
            'generated' => $generated,
            'rows' => $rows,
        ]));
    }

    public function payrollSummary(Request $request)
    {
        $this->authorizeView();

        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to = $request->input('to', now()->toDateString());
        $generated = $request->boolean('show');

        $rows = collect();
        $totals = ['gross' => 0, 'deductions' => 0, 'net' => 0];
        if ($generated) {
            $rows = HrPayrollItem::query()
                ->with(['employee', 'payrollRun'])
                ->whereHas('payrollRun', function ($query) use ($from, $to) {
                    $query->whereBetween('period_start', [$from, $to]);
                })
                ->orderBy('employee_id')
                ->get();

            $totals = [
                'gross' => round((float) $rows->sum('gross_pay'), 2),
                'deductions' => round((float) $rows->sum('deductions'), 2),
                'net' => round((float) $rows->sum('net_pay'), 2),
            ];
        }

        return view('hr.reports.payroll-summary', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'hr.reports',
            'from' => $from,
            'to' => $to,
            'generated' => $generated,
            'rows' => $rows,
            'totals' => $totals,
        ]));
    }

    public function paymentHistory(Request $request)
    {
        $this->authorizeView();

        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to = $request->input('to', now()->toDateString());
        $employeeId = $request->filled('employee_id') ? (int) $request->input('employee_id') : null;
        $generated = $request->boolean('show');

        $rows = collect();
        $total = 0;
        if ($generated) {
            $rows = HrSalaryPayment::query()
                ->with(['employee', 'payrollRun', 'user'])
                ->whereBetween('payment_date', [$from, $to])
                ->when($employeeId, function ($query) use ($employeeId) {
                    $query->where('employee_id', $employeeId);
                })
                ->orderByDesc('payment_date')
                ->get();
            $total = round((float) $rows->sum('amount'), 2);
        }

        return view('hr.reports.payment-history', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'hr.reports',
            'from' => $from,
            'to' => $to,
            'employeeId' => $employeeId,
            'generated' => $generated,
            'employees' => HrEmployee::query()->where('status', 'active')->orderBy('name')->get(),
            'rows' => $rows,
            'total' => $total,
        ]));
    }

    private function authorizeView(): void
    {
        $user = auth()->user();
        abort_unless($user && ($user->hasPermission('hr.view') || $user->hasPermission('users.view')), 403);
    }
}
