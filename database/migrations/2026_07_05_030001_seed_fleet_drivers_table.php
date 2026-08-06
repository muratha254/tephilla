<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fleet_drivers') || DB::table('fleet_drivers')->count() > 0) {
            return;
        }

        $now = now();
        $password = Hash::make('1234');

        $drivers = [
            ['name' => 'Driver 480', 'mobile' => '9988776106', 'email' => 'driver238@example.com', 'license_number' => 'TN01 175387', 'license_expiry' => '2031-01-08', 'age' => 32],
            ['name' => 'Driver 875', 'mobile' => '9988776107', 'email' => 'driver875@example.com', 'license_number' => 'TN01 284519', 'license_expiry' => '2030-06-15', 'age' => 28],
            ['name' => 'Driver 815', 'mobile' => '9988776108', 'email' => 'driver815@example.com', 'license_number' => 'TN01 392846', 'license_expiry' => '2031-03-22', 'age' => 35],
            ['name' => 'Driver 497', 'mobile' => '9988776109', 'email' => 'driver497@example.com', 'license_number' => 'TN01 401275', 'license_expiry' => '2030-11-30', 'age' => 41],
            ['name' => 'Driver 619', 'mobile' => '9988776110', 'email' => 'driver619@example.com', 'license_number' => 'TN01 518903', 'license_expiry' => '2031-08-14', 'age' => 29],
        ];

        foreach ($drivers as $driver) {
            DB::table('fleet_drivers')->insert(array_merge($driver, [
                'status' => 'Active',
                'password' => $password,
                'date_of_joining' => '2024-01-01',
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }

    public function down(): void
    {
        DB::table('fleet_drivers')->whereIn('email', [
            'driver238@example.com',
            'driver875@example.com',
            'driver815@example.com',
            'driver497@example.com',
            'driver619@example.com',
        ])->delete();
    }
};
