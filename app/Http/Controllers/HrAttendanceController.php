<?php

namespace App\Http\Controllers;

use App\Models\HrAttendance;
use App\Models\HrEmployee;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HrAttendanceController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeView();

        $query = HrAttendance::query()->with(['employee', 'branch', 'user'])->orderByDesc('attendance_date')->orderByDesc('id');

        if ($request->filled('attendance_date')) {
            $query->whereDate('attendance_date', $request->input('attendance_date'));
        }
        if ($request->filled('employee_id')) {
            $query->where('employee_id', (int) $request->input('employee_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return view('hr.attendance.index', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'hr.attendance',
            'records' => $query->get(),
            'employees' => HrEmployee::query()->where('status', 'active')->orderBy('name')->get(),
            'filters' => $request->only(['attendance_date', 'employee_id', 'status']),
            'statuses' => $this->statuses(),
            'canManage' => $this->canManage(),
        ]));
    }

    public function create()
    {
        $this->authorizeManage();

        return view('hr.attendance.form', array_merge(fleet_shared_view_data(), $this->formLookups(), [
            'activeMenu' => 'hr.attendance',
            'record' => new HrAttendance([
                'attendance_date' => now()->toDateString(),
                'status' => HrAttendance::STATUS_PRESENT,
                'branch_id' => $this->currentBranchId(),
            ]),
            'canManage' => true,
        ]));
    }

    public function store(Request $request)
    {
        $this->authorizeManage();
        $companyId = (int) auth()->user()->company_id;
        $data = $this->validateAttendance($request, $companyId);
        $employee = HrEmployee::query()->findOrFail($data['employee_id']);

        HrAttendance::query()->create([
            'company_id' => $companyId,
            'branch_id' => $data['branch_id'] ?? $employee->branch_id ?: $this->currentBranchId(),
            'employee_id' => $employee->id,
            'user_id' => auth()->id(),
            'attendance_date' => $data['attendance_date'],
            'clock_in' => $this->normalizeTime($data['clock_in'] ?? null),
            'clock_out' => $this->normalizeTime($data['clock_out'] ?? null),
            'status' => $data['status'],
            'hours_worked' => $this->computeHours($data['clock_in'] ?? null, $data['clock_out'] ?? null),
            'notes' => $data['notes'] ?? null,
        ]);

        return redirect()->route('hr.attendance.index')->with('success', 'Attendance saved.');
    }

    public function edit(HrAttendance $attendance)
    {
        $this->authorizeManage();

        return view('hr.attendance.form', array_merge(fleet_shared_view_data(), $this->formLookups(), [
            'activeMenu' => 'hr.attendance',
            'record' => $attendance,
            'canManage' => true,
        ]));
    }

    public function update(Request $request, HrAttendance $attendance)
    {
        $this->authorizeManage();
        $companyId = (int) auth()->user()->company_id;
        $data = $this->validateAttendance($request, $companyId, $attendance->id);
        $employee = HrEmployee::query()->findOrFail($data['employee_id']);

        $attendance->update([
            'branch_id' => $data['branch_id'] ?? $employee->branch_id ?: $this->currentBranchId(),
            'employee_id' => $employee->id,
            'attendance_date' => $data['attendance_date'],
            'clock_in' => $this->normalizeTime($data['clock_in'] ?? null),
            'clock_out' => $this->normalizeTime($data['clock_out'] ?? null),
            'status' => $data['status'],
            'hours_worked' => $this->computeHours($data['clock_in'] ?? null, $data['clock_out'] ?? null),
            'notes' => $data['notes'] ?? null,
        ]);

        return redirect()->route('hr.attendance.index')->with('success', 'Attendance updated.');
    }

    public function destroy(HrAttendance $attendance)
    {
        $this->authorizeManage();
        $attendance->delete();

        return redirect()->route('hr.attendance.index')->with('success', 'Attendance deleted.');
    }

    private function validateAttendance(Request $request, int $companyId, ?int $ignoreId = null): array
    {
        return $request->validate([
            'employee_id' => ['required', Rule::exists('hr_employees', 'id')->where('company_id', $companyId)],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('company_id', $companyId)],
            'attendance_date' => [
                'required',
                'date',
                Rule::unique('hr_attendances', 'attendance_date')
                    ->where(function ($query) use ($request, $companyId) {
                        return $query->where('company_id', $companyId)
                            ->where('employee_id', $request->input('employee_id'));
                    })
                    ->ignore($ignoreId),
            ],
            'clock_in' => 'nullable|string|max:8',
            'clock_out' => 'nullable|string|max:8',
            'status' => 'required|in:' . implode(',', array_keys($this->statuses())),
            'notes' => 'nullable|string|max:2000',
        ]);
    }

    private function normalizeTime(?string $time): ?string
    {
        if (! $time) {
            return null;
        }

        return substr($time, 0, 5);
    }

    private function computeHours(?string $clockIn, ?string $clockOut): ?float
    {
        $clockIn = $this->normalizeTime($clockIn);
        $clockOut = $this->normalizeTime($clockOut);

        if (! $clockIn || ! $clockOut) {
            return null;
        }

        try {
            $in = Carbon::createFromFormat('H:i', $clockIn);
            $out = Carbon::createFromFormat('H:i', $clockOut);
        } catch (\Exception $e) {
            return null;
        }

        if ($out->lessThan($in)) {
            $out->addDay();
        }

        return round($in->diffInMinutes($out) / 60, 2);
    }

    private function statuses(): array
    {
        return [
            HrAttendance::STATUS_PRESENT => 'Present',
            HrAttendance::STATUS_ABSENT => 'Absent',
            HrAttendance::STATUS_LATE => 'Late',
            HrAttendance::STATUS_LEAVE => 'Leave',
            HrAttendance::STATUS_HALF_DAY => 'Half Day',
        ];
    }

    private function formLookups(): array
    {
        return [
            'employees' => HrEmployee::query()->where('status', 'active')->orderBy('name')->get(),
            'statuses' => $this->statuses(),
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
