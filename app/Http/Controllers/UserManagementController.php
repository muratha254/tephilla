<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class UserManagementController extends Controller
{
    public function index()
    {
        $this->authorizePermission('users.view');

        $users = User::query()
            ->with(['role', 'branch'])
            ->when(auth()->user()->company_id, fn ($q) => $q->where('company_id', auth()->user()->company_id))
            ->orderBy('name')
            ->get();

        return view('users.index', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'users.index',
            'users' => $users,
            'canCreate' => auth()->user()->hasPermission('users.create'),
            'canUpdate' => auth()->user()->hasPermission('users.update'),
            'canDelete' => auth()->user()->hasPermission('users.delete'),
        ]));
    }

    public function create()
    {
        $this->authorizePermission('users.create');

        return view('users.form', array_merge(fleet_shared_view_data(), $this->formData(new User([
            'is_active' => true,
            'branch_id' => $this->currentBranchId(),
        ])), [
            'activeMenu' => 'users.create',
        ]));
    }

    public function store(Request $request, AuditLogger $audit)
    {
        $this->authorizePermission('users.create');

        $data = $this->validated($request);
        $user = User::query()->create([
            'company_id' => auth()->user()->company_id,
            'branch_id' => $data['branch_id'],
            'role_id' => $data['role_id'],
            'name' => $data['username'],
            'username' => $data['username'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'description' => $data['description'] ?? null,
            'password' => Hash::make($data['password']),
            'is_active' => true,
        ]);

        if ($request->hasFile('photo')) {
            $user->update([
                'profile_photo_path' => $request->file('photo')->store('profile-photos', 'public'),
            ]);
        }

        $audit->record('create', 'users', $user, null, $user->only(['username', 'email', 'role_id', 'branch_id']));

        return redirect()->route('users.index')->with('success', 'User created successfully.');
    }

    public function edit(User $user)
    {
        $this->authorizePermission('users.update');
        $this->assertSameCompany($user);

        return view('users.form', array_merge(fleet_shared_view_data(), $this->formData($user), [
            'activeMenu' => 'users.index',
        ]));
    }

    public function update(Request $request, User $user, AuditLogger $audit)
    {
        $this->authorizePermission('users.update');
        $this->assertSameCompany($user);

        $before = $user->only(['username', 'email', 'role_id', 'branch_id', 'phone', 'description', 'is_active']);
        $data = $this->validated($request, $user->id);

        $payload = [
            'branch_id' => $data['branch_id'],
            'role_id' => $data['role_id'],
            'name' => $data['username'],
            'username' => $data['username'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'description' => $data['description'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ];

        if (! empty($data['password'])) {
            $payload['password'] = Hash::make($data['password']);
        }

        $user->update($payload);

        if ($request->hasFile('photo')) {
            if ($user->profile_photo_path) {
                Storage::disk('public')->delete($user->profile_photo_path);
            }
            $user->update([
                'profile_photo_path' => $request->file('photo')->store('profile-photos', 'public'),
            ]);
        }

        $audit->record('update', 'users', $user, $before, $user->only(['username', 'email', 'role_id', 'branch_id', 'phone', 'description', 'is_active']));

        return redirect()->route('users.index')->with('success', 'User updated successfully.');
    }

    public function destroy(User $user, AuditLogger $audit)
    {
        $this->authorizePermission('users.delete');
        $this->assertSameCompany($user);

        abort_if((int) $user->id === (int) auth()->id(), 403, 'You cannot delete your own account.');
        abort_if($user->isSuperAdmin() && ! auth()->user()->isSuperAdmin(), 403);

        $before = $user->only(['username', 'email', 'role_id']);
        $user->delete();
        $audit->record('delete', 'users', $user, $before, null);

        return redirect()->route('users.index')->with('success', 'User deleted successfully.');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $companyId = auth()->user()->company_id;

        return $request->validate([
            'username' => [
                'required', 'string', 'max:100',
                Rule::unique('users', 'username')->ignore($ignoreId)->whereNull('deleted_at'),
            ],
            'email' => [
                'required', 'email', 'max:190',
                Rule::unique('users', 'email')->ignore($ignoreId)->whereNull('deleted_at'),
            ],
            'branch_id' => [
                'required',
                Rule::exists('branches', 'id')->where(function ($q) use ($companyId) {
                    if ($companyId) {
                        $q->where('company_id', $companyId);
                    }
                }),
            ],
            'role_id' => [
                'required',
                Rule::exists('roles', 'id')->where(function ($q) use ($companyId) {
                    if ($companyId) {
                        $q->where('company_id', $companyId);
                    }
                }),
            ],
            'phone' => ['nullable', 'string', 'max:40'],
            'description' => ['nullable', 'string', 'max:2000'],
            'password' => [$ignoreId ? 'nullable' : 'required', 'string', 'min:4', 'confirmed'],
            'photo' => ['nullable', 'image', 'max:2048'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    private function formData(User $user): array
    {
        $companyId = auth()->user()->company_id;

        return [
            'user' => $user,
            'branches' => Branch::query()
                ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
            'roles' => Role::query()
                ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
                ->orderBy('display_name')
                ->get(),
        ];
    }

    private function assertSameCompany(User $user): void
    {
        $companyId = auth()->user()->company_id;
        abort_if($companyId && (int) $user->company_id !== (int) $companyId, 404);
    }
}
