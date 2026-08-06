<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FleetEmployee extends Model
{
    protected $fillable = [
        'first_name',
        'last_name',
        'mobile',
        'email',
        'username',
        'password',
        'permissions',
        'is_active',
        'user_id',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'permissions' => 'array',
        'is_active' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function fullName(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    public function statusLabel(): string
    {
        return $this->is_active ? 'Active' : 'Inactive';
    }

    public function hasPermission(string $key): bool
    {
        return in_array($key, $this->permissions ?? [], true);
    }

    public static function permissionGroups(): array
    {
        return [
            'vehicle' => [
                'label' => 'Vehicle Management',
                'permissions' => [
                    'vehicle_view_list' => 'View Vehicle List',
                    'vehicle_view_details' => 'View Details',
                    'vehicle_edit' => 'Edit Vehicle',
                    'vehicle_add' => 'Add Vehicle',
                    'vehicle_delete' => 'Delete Vehicle',
                    'vehicle_groups' => 'Vehicle Groups',
                    'vehicle_add_group' => 'Add Group',
                    'vehicle_delete_group' => 'Delete Group',
                ],
            ],
            'drivers' => [
                'label' => 'Drivers',
                'permissions' => [
                    'driver_view' => 'View Drivers',
                    'driver_edit' => 'Edit Driver',
                    'driver_add' => 'Add Driver',
                    'driver_delete' => 'Delete Driver',
                ],
            ],
            'trips' => [
                'label' => 'Trips & Bookings',
                'permissions' => [
                    'trip_view' => 'View Trips',
                    'trip_add' => 'Add Trip',
                    'trip_edit' => 'Edit Trip',
                    'trip_delete' => 'Delete Trip',
                    'trip_dispatch' => 'Smart Dispatch',
                    'booking_view' => 'View Bookings',
                    'booking_add' => 'Add Booking',
                    'booking_edit' => 'Edit Booking',
                    'booking_delete' => 'Delete Booking',
                ],
            ],
            'customers' => [
                'label' => 'Customer',
                'permissions' => [
                    'customer_view' => 'View Customers',
                    'customer_add' => 'Add Customer',
                    'customer_edit' => 'Edit Customer',
                    'customer_delete' => 'Delete Customer',
                ],
            ],
            'maintenance' => [
                'label' => 'Maintenance',
                'permissions' => [
                    'maintenance_view' => 'View Maintenance',
                    'maintenance_add' => 'Add Maintenance',
                    'maintenance_edit' => 'Edit Maintenance',
                    'maintenance_delete' => 'Delete Maintenance',
                ],
            ],
            'fuel' => [
                'label' => 'Fuel',
                'permissions' => [
                    'fuel_view' => 'View Fuel',
                    'fuel_add' => 'Add Fuel',
                    'fuel_edit' => 'Edit Fuel',
                    'fuel_delete' => 'Delete Fuel',
                ],
            ],
            'reports' => [
                'label' => 'Reports',
                'permissions' => [
                    'reports_view' => 'View Reports',
                    'reports_export' => 'Export Reports',
                ],
            ],
            'tracking' => [
                'label' => 'Tracking',
                'permissions' => [
                    'tracking_live' => 'Live Fleet',
                    'tracking_history' => 'History Tracking',
                ],
            ],
        ];
    }

    public static function allPermissionKeys(): array
    {
        $keys = [];

        foreach (self::permissionGroups() as $group) {
            foreach ($group['permissions'] as $key => $label) {
                $keys[] = $key;
            }
        }

        return $keys;
    }
}
