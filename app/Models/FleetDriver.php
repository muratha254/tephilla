<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FleetDriver extends Model
{
    protected $fillable = [
        'name',
        'mobile',
        'email',
        'status',
        'age',
        'password',
        'date_of_joining',
        'photo_path',
        'license_number',
        'license_expiry',
        'license_doc',
        'id_number',
        'id_doc',
        'employment_type',
        'department',
        'employee_id',
        'contract_end_date',
        'salary',
        'payment_type',
        'bank_name',
        'bank_account',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'date_of_joining' => 'date',
        'license_expiry' => 'date',
        'contract_end_date' => 'date',
        'salary' => 'decimal:2',
    ];

    public function licenseExpiryDays(): ?int
    {
        if (! $this->license_expiry) {
            return null;
        }

        return now()->startOfDay()->diffInDays($this->license_expiry, false);
    }

    public function licenseExpiryLabel(): string
    {
        $days = $this->licenseExpiryDays();

        if ($days === null) {
            return '-';
        }

        if ($days < 0) {
            return 'Expired ' . abs($days) . ' days ago.';
        }

        return 'Expires in ' . $days . ' days.';
    }

    public function avatarInitials(): string
    {
        $name = trim((string) $this->name);

        if ($name === '') {
            return '?';
        }

        $words = preg_split('/\s+/', $name);
        $first = strtoupper(substr($words[0], 0, 1));

        if (preg_match('/(\d)/', $name, $matches)) {
            return $first . $matches[1];
        }

        if (isset($words[1])) {
            return $first . strtoupper(substr($words[1], 0, 1));
        }

        return $first;
    }

    public function storedFileUrl(?string $path): ?string
    {
        return $path ? asset('storage/' . $path) : null;
    }

    public function storedFileIsImage(?string $path): bool
    {
        if (! $path) {
            return false;
        }

        return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);
    }

    public function storedFileLabel(?string $path): string
    {
        return $path ? basename($path) : '';
    }
}
