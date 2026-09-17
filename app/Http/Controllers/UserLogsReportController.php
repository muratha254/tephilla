<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class UserLogsReportController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeUserLogsReport();

        $from = $request->input('from', now()->toDateString());
        $to = $request->input('to', now()->toDateString());
        $userId = $request->filled('user_id') ? (int) $request->input('user_id') : null;
        $generated = $request->boolean('show');
        $branchId = $this->currentBranchId();

        $rows = collect();
        if ($generated) {
            $rows = AuditLog::query()
                ->with('user')
                ->where('module', 'auth')
                ->whereIn('action', ['login', 'logout', 'failed_login'])
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
                    $after = is_array($log->after_json) ? $log->after_json : [];
                    $status = $after['status'] ?? match ($log->action) {
                        'login' => 'Success',
                        'logout' => 'Logged Out',
                        'failed_login' => 'Failed',
                        default => ucwords(str_replace('_', ' ', (string) $log->action)),
                    };

                    $username = $after['username']
                        ?? optional($log->user)->name
                        ?? optional($log->user)->email
                        ?? '-';

                    return [
                        'index' => $i + 1,
                        'username' => $username,
                        'date' => optional($log->created_at)->format('d-m-Y H:i:s') ?: '-',
                        'status' => $status,
                        'ip' => $log->ip_address ?: '-',
                        'system' => $this->systemLabel($log->user_agent),
                    ];
                })->values();
        }

        return view('reports.user-logs.index', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'reports.user-logs',
            'from' => $from,
            'to' => $to,
            'userId' => $userId,
            'generated' => $generated,
            'users' => $this->userOptions(),
            'rows' => $rows,
        ]));
    }

    private function systemLabel(?string $userAgent): string
    {
        $ua = strtolower((string) $userAgent);
        if ($ua === '') {
            return fleet_system_name();
        }
        if (str_contains($ua, 'mobile') || str_contains($ua, 'android') || str_contains($ua, 'iphone')) {
            return 'Mobile Browser';
        }

        return 'Web Browser';
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

    private function authorizeUserLogsReport(): void
    {
        $user = auth()->user();
        abort_unless($user && ($user->hasPermission('reports.audit') || $user->hasPermission('reports.view')), 403);
    }
}
