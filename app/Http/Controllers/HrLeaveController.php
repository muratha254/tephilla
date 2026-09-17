<?php

namespace App\Http\Controllers;

use App\Models\HrEmployee;
use App\Models\HrHoliday;
use App\Models\HrLeave;
use App\Models\HrLeaveAssignment;
use App\Models\HrLeaveType;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HrLeaveController extends Controller
{
    public function holidaysIndex()
    {
        $this->authorizeView();

        return view('hr.leave.holidays-index', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'hr.leave.holidays',
            'holidays' => HrHoliday::query()->orderByDesc('holiday_date')->get(),
            'canManage' => $this->canManage(),
        ]));
    }

    public function holidaysStore(Request $request)
    {
        $this->authorizeManage();
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'holiday_date' => 'required|date',
            'description' => 'nullable|string|max:2000',
        ]);

        HrHoliday::query()->create([
            'company_id' => (int) auth()->user()->company_id,
            'name' => $data['name'],
            'holiday_date' => $data['holiday_date'],
            'description' => $data['description'] ?? null,
            'is_active' => true,
        ]);

        return redirect()->route('hr.leave.holidays')->with('success', 'Holiday saved.');
    }

    public function holidaysUpdate(Request $request, HrHoliday $holiday)
    {
        $this->authorizeManage();
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'holiday_date' => 'required|date',
            'description' => 'nullable|string|max:2000',
            'is_active' => 'nullable|boolean',
        ]);

        $holiday->update([
            'name' => $data['name'],
            'holiday_date' => $data['holiday_date'],
            'description' => $data['description'] ?? null,
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $holiday->is_active,
        ]);

        return redirect()->route('hr.leave.holidays')->with('success', 'Holiday updated.');
    }

    public function holidaysDestroy(HrHoliday $holiday)
    {
        $this->authorizeManage();
        $holiday->delete();

        return redirect()->route('hr.leave.holidays')->with('success', 'Holiday deleted.');
    }

    public function typesIndex()
    {
        $this->authorizeView();

        return view('hr.leave.types-index', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'hr.leave.types',
            'types' => HrLeaveType::query()->orderBy('name')->get(),
            'canManage' => $this->canManage(),
        ]));
    }

    public function typesCreate()
    {
        $this->authorizeManage();

        return view('hr.leave.type-form', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'hr.leave.types.create',
            'type' => new HrLeaveType(['is_paid' => true, 'is_active' => true, 'days_allowed' => 0]),
            'canManage' => true,
        ]));
    }

    public function typesStore(Request $request)
    {
        $this->authorizeManage();
        $data = $this->validateLeaveType($request);

        HrLeaveType::query()->create([
            'company_id' => (int) auth()->user()->company_id,
            'name' => $data['name'],
            'days_allowed' => round((float) $data['days_allowed'], 2),
            'is_paid' => (bool) $data['is_paid'],
            'description' => $data['description'] ?? null,
            'is_active' => true,
        ]);

        return redirect()->route('hr.leave.types')->with('success', 'Leave type saved.');
    }

    public function typesEdit(HrLeaveType $type)
    {
        $this->authorizeManage();

        return view('hr.leave.type-form', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'hr.leave.types',
            'type' => $type,
            'canManage' => true,
        ]));
    }

    public function typesUpdate(Request $request, HrLeaveType $type)
    {
        $this->authorizeManage();
        $data = $this->validateLeaveType($request);

        $type->update([
            'name' => $data['name'],
            'days_allowed' => round((float) $data['days_allowed'], 2),
            'is_paid' => (bool) $data['is_paid'],
            'description' => $data['description'] ?? null,
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $type->is_active,
        ]);

        return redirect()->route('hr.leave.types')->with('success', 'Leave type updated.');
    }

    public function typesDestroy(HrLeaveType $type)
    {
        $this->authorizeManage();
        $type->delete();

        return redirect()->route('hr.leave.types')->with('success', 'Leave type deleted.');
    }

    public function assignmentsIndex()
    {
        $this->authorizeView();

        return view('hr.leave.assignments-index', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'hr.leave.assign',
            'assignments' => HrLeaveAssignment::query()->with(['employee', 'leaveType'])->orderByDesc('id')->get(),
            'employees' => HrEmployee::query()->where('status', 'active')->orderBy('name')->get(),
            'types' => HrLeaveType::query()->where('is_active', true)->orderBy('name')->get(),
            'canManage' => $this->canManage(),
        ]));
    }

    public function assignmentsStore(Request $request)
    {
        $this->authorizeManage();
        $companyId = (int) auth()->user()->company_id;
        $data = $request->validate([
            'employee_id' => ['required', Rule::exists('hr_employees', 'id')->where('company_id', $companyId)],
            'leave_type_id' => ['required', Rule::exists('hr_leave_types', 'id')->where('company_id', $companyId)],
            'year' => 'required|integer|min:2000|max:2100',
            'days_assigned' => 'required|numeric|min:0',
        ]);

        HrLeaveAssignment::query()->updateOrCreate(
            [
                'company_id' => $companyId,
                'employee_id' => $data['employee_id'],
                'leave_type_id' => $data['leave_type_id'],
                'year' => (int) $data['year'],
            ],
            [
                'days_assigned' => round((float) $data['days_assigned'], 2),
                'user_id' => auth()->id(),
            ]
        );

        return redirect()->route('hr.leave.assign')->with('success', 'Leave assigned.');
    }

    public function assignmentsDestroy(HrLeaveAssignment $assignment)
    {
        $this->authorizeManage();
        $assignment->delete();

        return redirect()->route('hr.leave.assign')->with('success', 'Leave assignment deleted.');
    }

    public function leavesIndex()
    {
        $this->authorizeView();

        return view('hr.leave.leaves-index', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'hr.leave.manage',
            'leaves' => HrLeave::query()->with(['employee', 'leaveType', 'user', 'branch'])->orderByDesc('id')->get(),
            'employees' => HrEmployee::query()->where('status', 'active')->orderBy('name')->get(),
            'types' => HrLeaveType::query()->where('is_active', true)->orderBy('name')->get(),
            'canManage' => $this->canManage(),
        ]));
    }

    public function leavesStore(Request $request)
    {
        $this->authorizeManage();
        $companyId = (int) auth()->user()->company_id;
        $data = $request->validate([
            'employee_id' => ['required', Rule::exists('hr_employees', 'id')->where('company_id', $companyId)],
            'leave_type_id' => ['required', Rule::exists('hr_leave_types', 'id')->where('company_id', $companyId)],
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'nullable|string|max:2000',
        ]);

        $employee = HrEmployee::query()->findOrFail($data['employee_id']);
        $start = Carbon::parse($data['start_date']);
        $end = Carbon::parse($data['end_date']);
        $days = $start->diffInDays($end) + 1;

        HrLeave::query()->create([
            'company_id' => $companyId,
            'branch_id' => $employee->branch_id ?: $this->currentBranchId(),
            'employee_id' => $employee->id,
            'leave_type_id' => $data['leave_type_id'],
            'user_id' => auth()->id(),
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'days' => $days,
            'reason' => $data['reason'] ?? null,
            'status' => 'pending',
        ]);

        return redirect()->route('hr.leave.manage')->with('success', 'Leave request saved.');
    }

    public function leavesUpdateStatus(Request $request, HrLeave $leave)
    {
        $this->authorizeManage();
        $data = $request->validate([
            'status' => 'required|in:pending,approved,rejected',
        ]);

        $previous = $leave->status;
        $leave->update(['status' => $data['status']]);

        if ($previous !== 'approved' && $data['status'] === 'approved') {
            $assignment = HrLeaveAssignment::query()->firstOrCreate(
                [
                    'company_id' => $leave->company_id,
                    'employee_id' => $leave->employee_id,
                    'leave_type_id' => $leave->leave_type_id,
                    'year' => (int) optional($leave->start_date)->format('Y'),
                ],
                [
                    'days_assigned' => 0,
                    'days_used' => 0,
                    'user_id' => auth()->id(),
                ]
            );
            $assignment->increment('days_used', (float) $leave->days);
        }

        if ($previous === 'approved' && $data['status'] !== 'approved') {
            $assignment = HrLeaveAssignment::query()
                ->where('employee_id', $leave->employee_id)
                ->where('leave_type_id', $leave->leave_type_id)
                ->where('year', (int) optional($leave->start_date)->format('Y'))
                ->first();
            if ($assignment) {
                $assignment->update([
                    'days_used' => max(0, (float) $assignment->days_used - (float) $leave->days),
                ]);
            }
        }

        return redirect()->route('hr.leave.manage')->with('success', 'Leave status updated.');
    }

    public function leavesDestroy(HrLeave $leave)
    {
        $this->authorizeManage();
        if ($leave->status === 'approved') {
            $assignment = HrLeaveAssignment::query()
                ->where('employee_id', $leave->employee_id)
                ->where('leave_type_id', $leave->leave_type_id)
                ->where('year', (int) optional($leave->start_date)->format('Y'))
                ->first();
            if ($assignment) {
                $assignment->update([
                    'days_used' => max(0, (float) $assignment->days_used - (float) $leave->days),
                ]);
            }
        }
        $leave->delete();

        return redirect()->route('hr.leave.manage')->with('success', 'Leave deleted.');
    }

    private function validateLeaveType(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'days_allowed' => 'required|numeric|min:0',
            'is_paid' => 'required|boolean',
            'description' => 'nullable|string|max:2000',
            'is_active' => 'nullable|boolean',
        ]);
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
