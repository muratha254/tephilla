<?php

$dir = __DIR__ . '/../app/Http/Controllers';
$files = glob($dir . '/Hr*.php');

$view = <<<'PHP'
    private function authorizeView(): void
    {
        $user = auth()->user();
        abort_unless($user && ($user->hasPermission('hr.view') || $user->hasPermission('users.view')), 403);
    }
PHP;

$manage = <<<'PHP'
    private function authorizeManage(): void
    {
        $user = auth()->user();
        abort_unless($user && (
            $user->hasPermission('hr.manage')
            || $user->hasPermission('hr.payroll')
            || $user->hasPermission('hr.attendance')
            || $user->hasPermission('users.create')
            || $user->hasPermission('users.update')
            || $user->hasPermission('users.view')
        ), 403);
    }
PHP;

$canManage = <<<'PHP'
    private function canManage(): bool
    {
        $user = auth()->user();

        return $user && (
            $user->hasPermission('hr.manage')
            || $user->hasPermission('hr.payroll')
            || $user->hasPermission('hr.attendance')
            || $user->hasPermission('users.create')
            || $user->hasPermission('users.update')
            || $user->hasPermission('users.view')
        );
    }
PHP;

foreach ($files as $file) {
    $c = file_get_contents($file);

    $c = preg_replace(
        '/abort_unless\(auth\(\)->user\(\) && auth\(\)->user\(\)->hasPermission\(\'hr\.view\'\) \|\| \$user->hasPermission\(\'users\.view\'\), 403\);/',
        "abort_unless((\$u = auth()->user()) && (\$u->hasPermission('hr.view') || \$u->hasPermission('users.view')), 403);",
        $c
    );

    $c = preg_replace('/    private function authorizeView\(\): void\s*\{.*?\n    \}/s', $view, $c);
    $c = preg_replace('/    private function authorizeManage\(\): void\s*\{.*?\n    \}/s', $manage, $c);
    $c = preg_replace('/    private function canManage\(\): bool\s*\{.*?\n    \}/s', $canManage, $c);

    file_put_contents($file, $c);
    echo basename($file) . " OK\n";
}
