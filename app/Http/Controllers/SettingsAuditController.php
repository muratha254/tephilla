<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

class SettingsAuditController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(
            auth()->user()->hasPermission('reports.audit')
            || auth()->user()->hasPermission('reports.view')
            || auth()->user()->hasPermission('settings.view'),
            403
        );

        $branchId = $this->currentBranchId();

        $logs = AuditLog::query()
            ->with('user')
            ->when($branchId, fn ($q) => $q->where(function ($query) use ($branchId) {
                $query->where('branch_id', $branchId)->orWhereNull('branch_id');
            }))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(2000)
            ->get()
            ->map(function (AuditLog $log, int $i) {
                $computer = $log->computer_json;
                if (! is_array($computer) || $computer === []) {
                    $computer = [
                        'hostname' => '-',
                        'os' => $log->user_agent ?: '-',
                        'ip' => $log->ip_address,
                    ];
                }

                return [
                    'index' => $i + 1,
                    'username' => optional($log->user)->username
                        ?: optional($log->user)->name
                        ?: optional($log->user)->email
                        ?: '-',
                    'action' => AuditLogger::actionLabel((string) $log->action, $log->module),
                    'computer' => json_encode($computer, JSON_UNESCAPED_SLASHES),
                    'datetime' => optional($log->created_at)->format('Y-m-d H:i:s') ?: '-',
                    'source' => AuditLogger::sourceLabel($log->auditable_type, $log->module),
                    'sort' => optional($log->created_at)->timestamp ?? 0,
                    'id' => $log->id,
                ];
            })->values();

        return view('settings.audit.index', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'settings.audit',
            'logs' => $logs,
        ]));
    }
}
