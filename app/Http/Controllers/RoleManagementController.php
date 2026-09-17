<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use App\Services\AuditLogger;
use App\Support\PermissionCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RoleManagementController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()->hasPermission('roles.view') || auth()->user()->hasPermission('roles.manage'), 403);

        $roles = Role::query()
            ->withCount('users')
            ->orderByDesc('is_system')
            ->orderBy('display_name')
            ->get();

        return view('roles.index', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'roles.index',
            'roles' => $roles,
            'canManage' => auth()->user()->hasPermission('roles.manage'),
        ]));
    }

    public function create()
    {
        $this->authorizePermission('roles.manage');

        return view('roles.form', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'roles.index',
            'role' => new Role(['is_system' => false]),
            'permissionGroups' => $this->permissionGroups(),
            'selectedPermissions' => old('permissions', []),
        ]));
    }

    public function store(Request $request, AuditLogger $audit)
    {
        $this->authorizePermission('roles.manage');

        $data = $this->validated($request);
        $slug = Str::slug($data['display_name'], '_');
        if ($slug === '') {
            $slug = 'role_' . time();
        }

        $role = Role::query()->create([
            'company_id' => auth()->user()->company_id,
            'name' => $this->uniqueRoleName($slug),
            'display_name' => $data['display_name'],
            'description' => $data['description'] ?? $data['display_name'],
            'is_system' => false,
        ]);

        $role->permissions()->sync($this->permissionIds($data['permissions'] ?? []));
        $audit->record('create', 'roles', $role, null, [
            'display_name' => $role->display_name,
            'permissions' => $data['permissions'] ?? [],
        ]);

        return redirect()->route('roles.index')->with('success', 'Role created successfully.');
    }

    public function edit(Role $role)
    {
        $this->authorizePermission('roles.manage');
        abort_if($role->is_system && $role->name === PermissionCatalog::SUPER_ADMIN && ! auth()->user()->isSuperAdmin(), 403);

        $role->load('permissions');

        return view('roles.form', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'roles.index',
            'role' => $role,
            'permissionGroups' => $this->permissionGroups(),
            'selectedPermissions' => old('permissions', $role->permissions->pluck('name')->all()),
        ]));
    }

    public function update(Request $request, Role $role, AuditLogger $audit)
    {
        $this->authorizePermission('roles.manage');
        abort_if($role->is_system && $role->name === PermissionCatalog::SUPER_ADMIN && ! auth()->user()->isSuperAdmin(), 403);

        $before = [
            'display_name' => $role->display_name,
            'description' => $role->description,
            'permissions' => $role->permissions()->pluck('name')->all(),
        ];

        $data = $this->validated($request, $role->id);

        $role->update([
            'display_name' => $data['display_name'],
            'description' => $data['description'] ?? $data['display_name'],
        ]);

        if (! $role->is_system || auth()->user()->isSuperAdmin()) {
            $role->permissions()->sync($this->permissionIds($data['permissions'] ?? []));
        }

        $audit->record('update', 'roles', $role, $before, [
            'display_name' => $role->display_name,
            'description' => $role->description,
            'permissions' => $data['permissions'] ?? [],
        ]);

        return redirect()->route('roles.index')->with('success', 'Role updated successfully.');
    }

    public function destroy(Role $role, AuditLogger $audit)
    {
        $this->authorizePermission('roles.manage');
        abort_if($role->is_system, 403, 'System roles cannot be deleted.');
        abort_if($role->users()->exists(), 422, 'Role is assigned to users and cannot be deleted.');

        $before = $role->only(['name', 'display_name']);
        $role->permissions()->detach();
        $role->delete();
        $audit->record('delete', 'roles', $role, $before, null);

        return redirect()->route('roles.index')->with('success', 'Role deleted successfully.');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $companyId = auth()->user()->company_id;

        return $request->validate([
            'display_name' => [
                'required', 'string', 'max:100',
                Rule::unique('roles', 'display_name')->ignore($ignoreId)->where(function ($q) use ($companyId) {
                    if ($companyId) {
                        $q->where('company_id', $companyId);
                    }
                }),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')],
        ]);
    }

    private function permissionGroups(): array
    {
        $groups = [];
        foreach (PermissionCatalog::permissions() as $name => $meta) {
            $module = $meta['module'] ?? 'general';
            $groups[$module][] = [
                'name' => $name,
                'label' => $meta['label'] ?? $name,
            ];
        }
        ksort($groups);

        return $groups;
    }

    private function permissionIds(array $names): array
    {
        if ($names === []) {
            return [];
        }

        return Permission::query()->whereIn('name', $names)->pluck('id')->all();
    }

    private function uniqueRoleName(string $slug): string
    {
        $base = Str::limit($slug, 50, '');
        $name = $base;
        $i = 1;
        $companyId = auth()->user()->company_id;

        while (Role::query()
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->where('name', $name)
            ->exists()) {
            $name = $base . '_' . $i;
            $i++;
        }

        return $name;
    }
}
