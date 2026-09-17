<?php

namespace App\Http\Controllers;

use App\Models\HrDepartment;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HrDepartmentController extends Controller
{
    public function index()
    {
        $this->authorizeView();

        return view('hr.departments.index', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'hr.departments.index',
            'departments' => HrDepartment::query()->orderBy('id')->get(),
            'canManage' => $this->canManage(),
        ]));
    }

    public function create()
    {
        $this->authorizeManage();

        return view('hr.departments.form', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'hr.departments.create',
            'department' => new HrDepartment(['is_active' => true]),
            'canManage' => true,
        ]));
    }

    public function store(Request $request)
    {
        $this->authorizeManage();
        $companyId = (int) auth()->user()->company_id;
        $data = $this->validateDepartment($request, $companyId);

        $department = HrDepartment::query()->create([
            'company_id' => $companyId,
            'code' => $data['code'] ?? null,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => (bool) $data['is_active'],
        ]);

        if ($department->code === null || $department->code === '') {
            $department->update(['code' => (string) $department->id]);
        }

        return redirect()->route('hr.departments.index')->with('success', 'Department saved.');
    }

    public function edit(HrDepartment $department)
    {
        $this->authorizeManage();

        return view('hr.departments.form', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'hr.departments.index',
            'department' => $department,
            'canManage' => true,
        ]));
    }

    public function update(Request $request, HrDepartment $department)
    {
        $this->authorizeManage();
        $companyId = (int) auth()->user()->company_id;
        $data = $this->validateDepartment($request, $companyId, $department->id);

        $department->update([
            'code' => $data['code'] ?: (string) $department->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => (bool) $data['is_active'],
        ]);

        return redirect()->route('hr.departments.index')->with('success', 'Department updated.');
    }

    public function destroy(HrDepartment $department)
    {
        $this->authorizeManage();
        $department->delete();

        return redirect()->route('hr.departments.index')->with('success', 'Department deleted.');
    }

    private function validateDepartment(Request $request, int $companyId, ?int $ignoreId = null): array
    {
        return $request->validate([
            'code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('hr_departments', 'code')
                    ->where(fn ($q) => $q->where('company_id', $companyId)->whereNull('deleted_at'))
                    ->ignore($ignoreId),
            ],
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'is_active' => 'required|boolean',
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
