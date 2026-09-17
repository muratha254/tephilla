<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class AuditTrailReportController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeAuditReport();

        $from = $request->input('from', now()->toDateString());
        $to = $request->input('to', now()->toDateString());
        $userId = $request->filled('user_id') ? (int) $request->input('user_id') : null;
        $generated = $request->boolean('show');
        $branchId = $this->currentBranchId();

        $rows = collect();
        if ($generated) {
            $rows = AuditLog::query()
                ->with('user')
                ->where(function ($q) {
                    $q->whereNull('module')->orWhere('module', '!=', 'auth');
                })
                ->whereBetween('created_at', [
                    Carbon::parse($from)->startOfDay(),
                    Carbon::parse($to)->endOfDay(),
                ])
                ->when($branchId, fn ($q) => $q->where(function ($query) use ($branchId) {
                    $query->where('branch_id', $branchId)->orWhereNull('branch_id');
                }))
                ->when($userId, fn ($q) => $q->where('user_id', $userId))
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->get()
                ->map(function (AuditLog $log, int $i) {
                    $table = $log->auditable_type
                        ? Str::afterLast(str_replace('\\', '/', (string) $log->auditable_type), '/')
                        : '-';

                    return [
                        'index' => $i + 1,
                        'username' => optional($log->user)->name ?: optional($log->user)->email ?: '-',
                        'date' => optional($log->created_at)->format('d-m-Y H:i:s') ?: '-',
                        'event' => ucwords(str_replace(['_', '-'], ' ', (string) $log->action)),
                        'system' => ucwords(str_replace(['_', '-'], ' ', (string) $log->module)),
                        'table' => $table,
                    ];
                })->values();
        }

        return view('reports.audit.index', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'reports.audit',
            'from' => $from,
            'to' => $to,
            'userId' => $userId,
            'generated' => $generated,
            'users' => $this->userOptions(),
            'rows' => $rows,
        ]));
    }

    private function userOptions()
    {
        $companyId = auth()->user()->company_id ?? null;

        return User::query()
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }

    private function authorizeAuditReport(): void
    {
        $user = auth()->user();
        abort_unless($user && ($user->hasPermission('reports.audit') || $user->hasPermission('reports.view')), 403);
    }
}
