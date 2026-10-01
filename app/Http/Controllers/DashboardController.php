<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Services\DashboardService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request, DashboardService $dashboard)
    {
        $user = auth()->user()->loadMissing(['role', 'company', 'branch']);

        if ($request->filled('branch_id') && $user->canSwitchBranches()) {
            $branch = Branch::query()->find((int) $request->input('branch_id'));
            if ($branch && (int) $branch->company_id === (int) $user->company_id) {
                session(['current_branch_id' => $branch->id]);
                app()->instance('currentBranchId', (int) $branch->id);
            }
        }

        $branchId = session('current_branch_id', $user->branch_id);
        $data = $dashboard->data($user, $branchId ? (int) $branchId : null);

        return view('dashboard.index', array_merge(fleet_shared_view_data(), $data, [
            'activeMenu' => 'dashboard',
            'user' => $user,
        ]));
    }

    public function switchBranch(Request $request)
    {
        abort_unless(auth()->user()->canSwitchBranches(), 403);

        $data = $request->validate([
            'branch_id' => 'required|integer|exists:branches,id',
        ]);

        $branch = Branch::query()->findOrFail($data['branch_id']);
        abort_unless(auth()->user()->canAccessBranch((int) $branch->id), 403);

        session(['current_branch_id' => $branch->id]);
        app()->instance('currentBranchId', (int) $branch->id);

        return back();
    }
}
