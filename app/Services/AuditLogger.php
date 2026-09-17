<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AuditLogger
{
    public function record(
        string $action,
        string $module,
        ?Model $record = null,
        ?array $before = null,
        ?array $after = null,
        ?Request $request = null
    ): AuditLog {
        $request = $request ?: request();
        $user = $request ? $request->user() : null;

        return AuditLog::query()->withoutGlobalScope('company')->create([
            'company_id' => $user->company_id ?? (app()->bound('currentCompanyId') ? app('currentCompanyId') : null),
            'branch_id' => $user->branch_id ?? (app()->bound('currentBranchId') ? app('currentBranchId') : null),
            'user_id' => $user->id ?? null,
            'action' => $action,
            'module' => $module,
            'auditable_type' => $record ? get_class($record) : null,
            'auditable_id' => $record->id ?? null,
            'before_json' => $before,
            'after_json' => $after,
            'ip_address' => $request ? $request->ip() : null,
            'user_agent' => $request ? substr((string) $request->userAgent(), 0, 1000) : null,
            'computer_json' => $this->computerSnapshot($request),
            'created_at' => now(),
        ]);
    }

    /**
     * @return array{hostname: string, os: string, ip: ?string}
     */
    private function computerSnapshot(?Request $request): array
    {
        return [
            'hostname' => gethostname() ?: php_uname('n'),
            'os' => php_uname(),
            'ip' => $request ? $request->ip() : null,
        ];
    }

    public static function sourceLabel(?string $auditableType, ?string $module = null): string
    {
        if ($auditableType) {
            $short = Str::afterLast(str_replace('\\', '/', $auditableType), '/');
            $table = Str::snake(Str::pluralStudly($short));

            return 'db_' . $table;
        }

        if ($module) {
            return 'db_' . Str::snake($module);
        }

        return '-';
    }

    public static function actionLabel(string $action, ?string $module = null): string
    {
        $action = str_replace([' ', '-'], '_', strtolower($action));
        if ($module && ! str_contains($action, strtolower($module))) {
            return Str::snake($module) . '_' . $action;
        }

        return $action;
    }
}
