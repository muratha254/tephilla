<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class BranchSettingsController extends Controller
{
    public function index()
    {
        abort_unless($this->canView(), 403);

        return view('settings.branches.index', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'settings.branches',
            'branches' => Branch::query()->orderByDesc('is_default')->orderBy('name')->get(),
            'canManage' => $this->canManage(),
        ]));
    }

    public function create()
    {
        abort_unless($this->canManage(), 403);

        return view('settings.branches.form', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'settings.branches',
            'branch' => new Branch([
                'is_active' => true,
                'list_on_login' => true,
                'system_mode' => 'Touch Mode(Normal)',
                'include_catering_levy' => false,
                'auto_receipt_amt_pos' => false,
            ]),
        ]));
    }

    public function store(Request $request)
    {
        abort_unless($this->canManage(), 403);

        $data = $this->validated($request);
        $data['company_id'] = auth()->user()->company_id;
        $data['logo_path'] = $this->storeLogo($request);

        $branch = Branch::query()->create($data);
        if ($branch->is_default) {
            $this->clearOtherDefaults($branch->id);
        }

        return redirect()->route('settings.branches')->with('success', 'Branch saved.');
    }

    public function edit(Branch $branch)
    {
        abort_unless($this->canManage(), 403);

        return view('settings.branches.form', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'settings.branches',
            'branch' => $branch,
        ]));
    }

    public function update(Request $request, Branch $branch)
    {
        abort_unless($this->canManage(), 403);

        $data = $this->validated($request, $branch->id);

        if ($request->boolean('remove_logo') && $branch->logo_path) {
            Storage::disk('public')->delete($branch->logo_path);
            $data['logo_path'] = null;
        }

        if ($request->hasFile('logo')) {
            if ($branch->logo_path) {
                Storage::disk('public')->delete($branch->logo_path);
            }
            $data['logo_path'] = $request->file('logo')->store('branch-logos', 'public');
        }

        $branch->update($data);
        if ($branch->is_default) {
            $this->clearOtherDefaults($branch->id);
        }

        return redirect()->route('settings.branches')->with('success', 'Branch updated.');
    }

    public function destroy(Branch $branch)
    {
        abort_unless($this->canManage(), 403);
        abort_if($branch->is_default, 422, 'Default branch cannot be deleted.');
        abort_if($branch->users()->exists(), 422, 'Branch has users and cannot be deleted.');

        if ($branch->logo_path) {
            Storage::disk('public')->delete($branch->logo_path);
        }

        $branch->delete();

        return redirect()->route('settings.branches')->with('success', 'Branch deleted.');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $companyId = auth()->user()->company_id;

        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:120',
                Rule::unique('branches', 'name')->ignore($ignoreId)->where(fn ($q) => $q->where('company_id', $companyId)->whereNull('deleted_at')),
            ],
            'code' => [
                'required', 'string', 'max:32',
                Rule::unique('branches', 'code')->ignore($ignoreId)->where(fn ($q) => $q->where('company_id', $companyId)->whereNull('deleted_at')),
            ],
            'system_mode' => ['required', 'string', 'max:64'],
            'phone' => ['required', 'string', 'max:40'],
            'phone_alt' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:190'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'postcode' => ['nullable', 'string', 'max:40'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'till1' => ['nullable', 'string', 'max:64'],
            'account1' => ['nullable', 'string', 'max:64'],
            'till2' => ['nullable', 'string', 'max:64'],
            'account2' => ['nullable', 'string', 'max:64'],
            'bank_account_name' => ['nullable', 'string', 'max:120'],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'bank_account_no' => ['nullable', 'string', 'max:64'],
            'bank_branch' => ['nullable', 'string', 'max:120'],
            'bank_code' => ['nullable', 'string', 'max:40'],
            'bank_swift' => ['nullable', 'string', 'max:40'],
            'include_catering_levy' => ['required', 'in:Yes,No'],
            'auto_receipt_amt_pos' => ['required', 'in:Yes,No'],
            'list_on_login' => ['nullable', 'in:Yes,No'],
            'is_active' => ['nullable', 'in:Yes,No'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        unset($data['logo']);

        $data['include_catering_levy'] = ($data['include_catering_levy'] ?? 'No') === 'Yes';
        $data['auto_receipt_amt_pos'] = ($data['auto_receipt_amt_pos'] ?? 'No') === 'Yes';
        $data['list_on_login'] = ($request->input('list_on_login', 'Yes') === 'Yes');
        $data['is_active'] = ($request->input('is_active', 'Yes') === 'Yes');
        $data['is_default'] = $request->boolean('is_default');

        return $data;
    }

    private function storeLogo(Request $request): ?string
    {
        if (! $request->hasFile('logo')) {
            return null;
        }

        return $request->file('logo')->store('branch-logos', 'public');
    }

    private function clearOtherDefaults(int $keepId): void
    {
        Branch::query()->where('id', '!=', $keepId)->update(['is_default' => false]);
    }

    private function canView(): bool
    {
        $user = auth()->user();

        return $user && (
            $user->hasPermission('settings.branches')
            || $user->hasPermission('branches.view')
            || $user->hasPermission('settings.view')
        );
    }

    private function canManage(): bool
    {
        $user = auth()->user();

        return $user && (
            $user->hasPermission('settings.branches')
            || $user->hasPermission('branches.manage')
        );
    }
}
