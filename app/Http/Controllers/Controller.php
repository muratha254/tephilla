<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Brand;
use App\Models\ProductCategory;
use App\Models\Tax;
use App\Models\Unit;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    protected function authorizePermission(string $permission): void
    {
        abort_unless(auth()->user() && auth()->user()->hasPermission($permission), 403);
    }

    protected function currentBranchId(): ?int
    {
        $user = auth()->user();
        if ($user && ! $user->canSwitchBranches()) {
            return $user->branch_id ? (int) $user->branch_id : null;
        }

        $id = session('current_branch_id', optional($user)->branch_id);

        return $id ? (int) $id : null;
    }

    /**
     * Active branch for list/write actions (respects admin branch switch).
     */
    protected function resolveBranchId(?int $requested = null): ?int
    {
        return $this->currentBranchId();
    }

    protected function assertBranchAccess(int $branchId): void
    {
        abort_unless((int) $branchId === (int) $this->currentBranchId(), 403, 'You cannot access another branch.');
    }

    protected function catalogLookups(): array
    {
        $branches = Branch::query()->where('is_active', true)->orderBy('name')->get();
        $user = auth()->user();
        if ($user && ! $user->canSwitchBranches()) {
            $branches = $branches->where('id', $user->branch_id)->values();
        }

        return [
            'branches' => $branches,
            'categories' => ProductCategory::query()->where('is_active', true)->orderBy('name')->get(),
            'brands' => Brand::query()->where('is_active', true)->orderBy('name')->get(),
            'units' => Unit::query()->where('is_active', true)->orderBy('name')->get(),
            'taxes' => Tax::query()->where('is_active', true)->orderBy('name')->get(),
        ];
    }
}
