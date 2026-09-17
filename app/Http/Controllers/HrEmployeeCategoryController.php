<?php

namespace App\Http\Controllers;

use App\Models\HrEmployeeCategory;
use Illuminate\Http\Request;

class HrEmployeeCategoryController extends Controller
{
    public function index()
    {
        $this->authorizeView();

        return view('hr.employee-categories.index', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'hr.employee-categories.index',
            'categories' => HrEmployeeCategory::query()->orderBy('id')->get(),
            'canManage' => $this->canManage(),
        ]));
    }

    public function create()
    {
        $this->authorizeManage();

        return view('hr.employee-categories.form', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'hr.employee-categories.create',
            'category' => new HrEmployeeCategory(['is_active' => true]),
            'canManage' => true,
        ]));
    }

    public function store(Request $request)
    {
        $this->authorizeManage();
        $data = $this->validateCategory($request);

        HrEmployeeCategory::query()->create([
            'company_id' => (int) auth()->user()->company_id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => true,
        ]);

        return redirect()->route('hr.employee-categories.index')->with('success', 'Employee category saved.');
    }

    public function edit(HrEmployeeCategory $category)
    {
        $this->authorizeManage();

        return view('hr.employee-categories.form', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'hr.employee-categories.index',
            'category' => $category,
            'canManage' => true,
        ]));
    }

    public function update(Request $request, HrEmployeeCategory $category)
    {
        $this->authorizeManage();
        $data = $this->validateCategory($request);

        $category->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $category->is_active,
        ]);

        return redirect()->route('hr.employee-categories.index')->with('success', 'Employee category updated.');
    }

    public function destroy(HrEmployeeCategory $category)
    {
        $this->authorizeManage();
        $category->delete();

        return redirect()->route('hr.employee-categories.index')->with('success', 'Employee category deleted.');
    }

    private function validateCategory(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
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
