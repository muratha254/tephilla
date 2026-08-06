<?php

namespace App\Http\Controllers;

use App\Models\FleetEmployee;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class FleetEmployeeController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));

        $employees = FleetEmployee::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('mobile', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%");
                });
            })
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        return view('fleet.employees.index', array_merge($this->sharedViewData(), [
            'activeMenu' => 'employee-list',
            'openMenu' => 'employee',
            'employees' => $employees,
            'search' => $search,
        ]));
    }

    public function create()
    {
        return view('fleet.employees.create', array_merge($this->sharedViewData(), [
            'activeMenu' => 'employee-add',
            'openMenu' => 'employee',
            'employee' => null,
            'permissionGroups' => FleetEmployee::permissionGroups(),
        ]));
    }

    public function store(Request $request)
    {
        $data = $this->validateEmployee($request);

        $employee = new FleetEmployee();
        $employee->fill($data);
        $employee->password = Hash::make($data['password']);
        $employee->permissions = $this->normalizePermissions($request);
        $employee->is_active = true;
        $employee->save();

        $this->syncUserAccount($employee, $data['password']);

        return redirect()
            ->route('employees.index')
            ->with('success', 'Employee created successfully.');
    }

    public function edit(FleetEmployee $employee)
    {
        return view('fleet.employees.edit', array_merge($this->sharedViewData(), [
            'activeMenu' => 'employee-list',
            'openMenu' => 'employee',
            'employee' => $employee,
            'permissionGroups' => FleetEmployee::permissionGroups(),
        ]));
    }

    public function update(Request $request, FleetEmployee $employee)
    {
        $data = $this->validateEmployee($request, $employee);

        $employee->fill($data);

        if (! empty($data['password'])) {
            $employee->password = Hash::make($data['password']);
        }

        $employee->permissions = $this->normalizePermissions($request);
        $employee->save();

        $this->syncUserAccount($employee, $data['password'] ?? null);

        return redirect()
            ->route('employees.index')
            ->with('success', 'Employee updated successfully.');
    }

    public function destroy(FleetEmployee $employee)
    {
        if ($employee->user_id) {
            User::query()->whereKey($employee->user_id)->delete();
        }

        $employee->delete();

        return redirect()
            ->route('employees.index')
            ->with('success', 'Employee deleted successfully.');
    }

    private function validateEmployee(Request $request, ?FleetEmployee $employee = null): array
    {
        $rules = [
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'mobile' => 'required|string|max:30',
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('fleet_employees', 'email')->ignore($employee?->id),
                Rule::unique('users', 'email')->ignore($employee?->user_id),
            ],
            'username' => [
                'required',
                'string',
                'max:100',
                Rule::unique('fleet_employees', 'username')->ignore($employee?->id),
            ],
            'password' => $employee
                ? 'nullable|string|min:4|max:100'
                : 'required|string|min:4|max:100',
        ];

        return $request->validate($rules);
    }

    private function normalizePermissions(Request $request): array
    {
        $selected = $request->input('permissions', []);
        $allowed = FleetEmployee::allPermissionKeys();

        return array_values(array_intersect($allowed, is_array($selected) ? $selected : []));
    }

    private function syncUserAccount(FleetEmployee $employee, ?string $plainPassword = null): void
    {
        $user = $employee->user_id
            ? User::query()->find($employee->user_id)
            : new User();

        $user->name = $employee->fullName();
        $user->email = $employee->email;
        $user->role = 'manager';
        $user->level = 2;
        $user->can_read = true;
        $user->foto = $user->foto ?: '/img/user.png';

        if ($plainPassword) {
            $user->password = Hash::make($plainPassword);
        } elseif (! $user->exists) {
            $user->password = $employee->password;
        }

        $user->save();

        if ($employee->user_id !== $user->id) {
            $employee->user_id = $user->id;
            $employee->save();
        }
    }

    private function sharedViewData(): array
    {
        return [
            'companyName' => 'One Translines Pvt Ltd',
            'notificationCount' => 11,
        ];
    }
}
