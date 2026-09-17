<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Support\Carbon;

class UserAuthLogsController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()->hasPermission('users.view') || auth()->user()->hasPermission('reports.audit'), 403);

        $branchId = $this->currentBranchId();

        $logs = AuditLog::query()
            ->with(['user.branch', 'user'])
            ->where('module', 'auth')
            ->whereIn('action', ['login', 'logout', 'failed_login'])
            ->when($branchId, fn ($q) => $q->where(function ($query) use ($branchId) {
                $query->where('branch_id', $branchId)->orWhereNull('branch_id');
            }))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(1000)
            ->get()
            ->map(function (AuditLog $log, int $i) {
                $after = is_array($log->after_json) ? $log->after_json : [];
                $status = $after['status'] ?? match ($log->action) {
                    'login' => 'Success',
                    'logout' => 'Logged Out',
                    'failed_login' => 'Failed!! Invalid Password!!',
                    default => ucwords(str_replace('_', ' ', (string) $log->action)),
                };

                return [
                    'index' => $i + 1,
                    'username' => $after['username']
                        ?? optional($log->user)->username
                        ?? optional($log->user)->name
                        ?? '-',
                    'datetime' => optional($log->created_at)->format('Y-m-d h:i:s a') ?: '-',
                    'status' => $status,
                    'ip' => $log->ip_address ?: '-',
                    'system_name' => $after['system_name'] ?? $this->systemName($log->ip_address, $log->user_agent),
                    'branch' => $after['branch']
                        ?? optional(optional($log->user)->branch)->name
                        ?? '-',
                    'sort' => optional($log->created_at)->timestamp ?? 0,
                ];
            })->values();

        return view('users.logs', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'users.logs',
            'logs' => $logs,
        ]));
    }

    private function systemName(?string $ip, ?string $userAgent): string
    {
        if ($ip && filter_var($ip, FILTER_VALIDATE_IP) && ! in_array($ip, ['127.0.0.1', '::1'], true)) {
            $host = @gethostbyaddr($ip);
            if (is_string($host) && $host !== '' && $host !== $ip) {
                return $host;
            }

            return $ip;
        }

        $ua = strtolower((string) $userAgent);
        if (str_contains($ua, 'mobile') || str_contains($ua, 'android') || str_contains($ua, 'iphone')) {
            return 'Mobile Browser';
        }

        return fleet_system_name();
    }
}
