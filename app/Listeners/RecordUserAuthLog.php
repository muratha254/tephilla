<?php

namespace App\Listeners;

use App\Services\AuditLogger;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;

class RecordUserAuthLog
{
    public function handle($event): void
    {
        $audit = app(AuditLogger::class);
        $request = request();

        if ($event instanceof Login) {
            $event->user->loadMissing('branch');
            $audit->record('login', 'auth', $event->user, null, [
                'username' => $event->user->username ?? $event->user->name ?? $event->user->email,
                'status' => 'Success',
                'branch' => optional($event->user->branch)->name,
                'system_name' => $this->systemName($request ? $request->ip() : null),
            ], $request);

            return;
        }

        if ($event instanceof Logout) {
            if (! $event->user) {
                return;
            }

            $audit->record('logout', 'auth', $event->user, null, [
                'username' => $event->user->username ?? $event->user->name ?? $event->user->email,
                'status' => 'Logged Out',
                'branch' => optional($event->user->branch)->name,
                'system_name' => $this->systemName($request ? $request->ip() : null),
            ], $request);

            return;
        }

        if ($event instanceof Failed) {
            $login = $event->credentials['email']
                ?? $event->credentials['username']
                ?? $event->credentials['login']
                ?? 'unknown';

            $audit->record('failed_login', 'auth', $event->user, null, [
                'username' => $login,
                'status' => 'Failed!! Invalid Password!!',
                'branch' => optional(optional($event->user)->branch)->name,
                'system_name' => $this->systemName($request ? $request->ip() : null),
            ], $request);
        }
    }

    private function systemName(?string $ip): string
    {
        if ($ip && filter_var($ip, FILTER_VALIDATE_IP) && ! in_array($ip, ['127.0.0.1', '::1'], true)) {
            $host = @gethostbyaddr($ip);
            if (is_string($host) && $host !== '' && $host !== $ip) {
                return $host;
            }

            return $ip;
        }

        return fleet_system_name();
    }
}
