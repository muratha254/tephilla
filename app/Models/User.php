<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use HasProfilePhoto;
    use Notifiable;
    use TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'can_create',
        'can_read',
        'can_update',
        'can_delete',
        'inv_create', 'inv_read', 'inv_update', 'inv_delete',
        'rep_create', 'rep_read', 'rep_update', 'rep_delete',
        'sal_create', 'sal_read', 'sal_update', 'sal_delete',
        'exp_create', 'exp_read', 'exp_update', 'exp_delete',
        'con_create', 'con_read', 'con_update', 'con_delete',
        'pay_create', 'pay_read', 'pay_update', 'pay_delete',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'can_create' => 'boolean',
        'can_read' => 'boolean',
        'can_update' => 'boolean',
        'can_delete' => 'boolean',
        'inv_create' => 'boolean', 'inv_read' => 'boolean', 'inv_update' => 'boolean', 'inv_delete' => 'boolean',
        'rep_create' => 'boolean', 'rep_read' => 'boolean', 'rep_update' => 'boolean', 'rep_delete' => 'boolean',
        'sal_create' => 'boolean', 'sal_read' => 'boolean', 'sal_update' => 'boolean', 'sal_delete' => 'boolean',
        'exp_create' => 'boolean', 'exp_read' => 'boolean', 'exp_update' => 'boolean', 'exp_delete' => 'boolean',
        'con_create' => 'boolean', 'con_read' => 'boolean', 'con_update' => 'boolean', 'con_delete' => 'boolean',
        'pay_create' => 'boolean', 'pay_read' => 'boolean', 'pay_update' => 'boolean', 'pay_delete' => 'boolean',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'profile_photo_url',
    ];

    public const ROLE_ADMIN = 'admin';
    public const ROLE_MANAGER = 'manager';
    public const ROLE_CASHIER = 'cashier';

    public function hasRole(string $role): bool
    {
        return strtolower((string) ($this->role ?? '')) === strtolower($role);
    }

    public function hasAnyRole(array $roles): bool
    {
        $current = strtolower((string) ($this->role ?? ''));
        foreach ($roles as $role) {
            if ($current === strtolower((string) $role)) {
                return true;
            }
        }
        return false;
    }

    public function canCreate(): bool { return (bool) ($this->can_create ?? false); }
    public function canRead(): bool { return (bool) ($this->can_read ?? true); }
    public function canUpdate(): bool { return (bool) ($this->can_update ?? false); }
    public function canDelete(): bool { return (bool) ($this->can_delete ?? false); }

    public function hasPermission(string $permission): bool
    {
        $map = [
            'create' => 'can_create',
            'read' => 'can_read',
            'update' => 'can_update',
            'delete' => 'can_delete',
        ];
        return isset($map[$permission]) ? (bool) ($this->{$map[$permission]} ?? false) : false;
    }

    public function hasModulePermission(string $module, string $action): bool
    {
        $module = strtolower($module);
        $action = strtolower($action);

        $moduleMap = [
            'inventory' => [
                'create' => 'inv_create',
                'read' => 'inv_read',
                'update' => 'inv_update',
                'delete' => 'inv_delete',
            ],
            'reports' => [
                'create' => 'rep_create',
                'read' => 'rep_read',
                'update' => 'rep_update',
                'delete' => 'rep_delete',
            ],
            'sales' => [
                'create' => 'sal_create',
                'read' => 'sal_read',
                'update' => 'sal_update',
                'delete' => 'sal_delete',
            ],
            'expense' => [
                'create' => 'exp_create',
                'read' => 'exp_read',
                'update' => 'exp_update',
                'delete' => 'exp_delete',
            ],
            'consignment' => [
                'create' => 'con_create',
                'read' => 'con_read',
                'update' => 'con_update',
                'delete' => 'con_delete',
            ],
            'payroll' => [
                'create' => 'pay_create',
                'read' => 'pay_read',
                'update' => 'pay_update',
                'delete' => 'pay_delete',
            ],
        ];

        if (isset($moduleMap[$module][$action])) {
            $field = $moduleMap[$module][$action];
            return (bool) ($this->{$field} ?? false);
        }

        return $this->hasPermission($action);
    }

    // Keep compatibility with existing code filtering non-admins
    public function scopeIsNotAdmin($query)
    {
        return $query->where('level', '!=', 1);
    }

    public function sentMessages()
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function receivedMessages()
    {
        return $this->hasMany(Message::class, 'receiver_id');
    }
}
