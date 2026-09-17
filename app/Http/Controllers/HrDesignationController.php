<?php

namespace App\Http\Controllers;

use App\Models\HrDepartment;
use App\Models\HrDesignation;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HrDesignationController extends Controller
{
    public function index()
    {
        $this->authorizeView();

        return view('hr.designations.index', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'hr.designations.index',
            'designations' => HrDesignation::query()->with('department')->orderBy('id')->get(),
            'canManage' => $this->canManage(),
        ]));
    }

    public function create()
    {
        $this->authorizeManage();

        return view('hr.designations.form', array_merge(fleet_shared_view_data(), $this->formLookups(), [
            'activeMenu' => 'hr.designations.create',
            'designation' => new HrDesignation(['is_active' => true]),
            'canManage' => true,
        ]));
    }

    public function store(Request $request)
    {
        $this->authorizeManage();
        $companyId = (int) auth()->user()->company_id;
        $data = $this->validateDesignation($request, $companyId);

        HrDesignation::query()->create([
            'company_id' => $companyId,
            'department_id' => $data['department_id'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => true,
        ]);

        return redirect()->route('hr.designations.index')->with('success', 'Designation saved.');
    }

    public function edit(HrDesignation $designation)
    {
        $this->authorizeManage();

        return view('hr.designations.form', array_merge(fleet_shared_view_data(), $this->formLookups(), [
            'activeMenu' => 'hr.designations.index',
            'designation' => $designation,
            'canManage' => true,
        ]));
    }

    public function update(Request $request, HrDesignation $designation)
    {
        $this->authorizeManage();
        $companyId = (int) auth()->user()->company_id;
        $data = $this->validateDesignation($request, $companyId);

        $designation->update([
            'department_id' => $data['department_id'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $designation->is_active,
        ]);

        return redirect()->route('hr.designations.index')->with('success', 'Designation updated.');
    }

    public function destroy(HrDesignation $designation)
    {
        $this->authorizeManage();
        $designation->delete();

        return redirect()->route('hr.designations.index')->with('success', 'Designation deleted.');
    }

    private function validateDesignation(Request $request, int $companyId): array
    {
        return $request->validate([
            'department_id' => ['required', Rule::exists('hr_departments', 'id')->where('company_id', $companyId)],
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'is_active' => 'nullable|boolean',
        ]);
    }

    private function formLookups(): array
    {
        return [
            'departments' => HrDepartment::query()->where('is_active', true)->orderBy('name')->get(),
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
