<?php

namespace App\Models;

use App\Support\PermissionCatalog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use HasProfilePhoto;
    use Notifiable;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'branch_id',
        'role_id',
        'name',
        'email',
        'username',
        'password',
        'phone',
        'description',
        'is_active',
        'last_login_at',
        'profile_photo_path',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    protected $appends = [
        'profile_photo_url',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role && $this->role->name === PermissionCatalog::SUPER_ADMIN;
    }

    public function isCompanyAdmin(): bool
    {
        return $this->role && in_array($this->role->name, [
            PermissionCatalog::SUPER_ADMIN,
            PermissionCatalog::COMPANY_ADMIN,
        ], true);
    }

    /**
     * Company admins (including super admin) may switch the working branch.
     * Data is always scoped to the active branch after switching.
     */
    public function canSwitchBranches(): bool
    {
        return $this->isCompanyAdmin();
    }

    public function hasRole(string $role): bool
    {
        return $this->role && strcasecmp((string) $this->role->name, $role) === 0;
    }

    public function hasAnyRole(array $roles): bool
    {
        if (! $this->role) {
            return false;
        }

        foreach ($roles as $role) {
            if (strcasecmp((string) $this->role->name, (string) $role) === 0) {
                return true;
            }
        }

        return false;
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if (! $this->role) {
            return false;
        }

        if ($this->relationLoaded('role') && $this->role->relationLoaded('permissions')) {
            return $this->role->permissions->contains('name', $permission);
        }

        return $this->role->permissions()->where('name', $permission)->exists();
    }

    public function canAccessModule(string $module, string $action = 'view'): bool
    {
        return $this->hasPermission($module . '.' . $action);
    }
}
