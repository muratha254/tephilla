<?php

namespace App\Http\Middleware;

use App\Models\Branch;
use Closure;
use Illuminate\Http\Request;

class SetCurrentCompany
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && $user->company_id) {
            if (! $user->is_active) {
                auth()->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')->with('error', 'This account is inactive.');
            }

            $user->loadMissing(['role.permissions', 'company', 'branch']);

            if ($user->isSystemOwner()) {
                return $next($request);
            }

            if (! $user->company_id) {
                return $next($request);
            }

            app()->instance('currentCompanyId', (int) $user->company_id);

            $branchId = $request->session()->get('current_branch_id', $user->branch_id);

            if (! $user->canSwitchBranches()) {
                $branchId = $user->branch_id;
                $request->session()->put('current_branch_id', $branchId);
            } elseif ($branchId) {
                $belongs = $user->isCompanyAdmin()
                    ? Branch::query()->whereKey($branchId)->where('company_id', $user->company_id)->exists()
                    : $user->canAccessBranch((int) $branchId);

                if (! $belongs) {
                    $branchId = $user->branch_id;
                    $request->session()->put('current_branch_id', $branchId);
                }
            }

            if ($branchId) {
                app()->instance('currentBranchId', (int) $branchId);
            }
        }

        return $next($request);
    }
}
