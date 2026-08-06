<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fleet_customers') || DB::table('fleet_customers')->count() > 0) {
            return;
        }

        $now = now();
        $rows = [
            ['name' => 'Jk', 'mobile' => '9876543210', 'whatsapp' => '9876543210', 'email' => 'jk@example.com', 'address' => 'Chennai, India'],
            ['name' => 'Demo Customer 320', 'mobile' => '9123456780', 'whatsapp' => '9123456780', 'email' => 'demo320@example.com', 'address' => 'Chennai, India'],
            ['name' => 'Metro Freight', 'mobile' => '9000012345', 'whatsapp' => '9000012345', 'email' => 'metro@example.com', 'address' => 'Madurai, Tamil Nadu, India'],
            ['name' => 'Acme Logistics', 'mobile' => '9776655443', 'whatsapp' => '9776655443', 'email' => 'acme@example.com', 'address' => 'Pune, Maharashtra, India'],
        ];

        foreach ($rows as $row) {
            DB::table('fleet_customers')->insert(array_merge($row, [
                'whatsapp_same_as_mobile' => true,
                'password' => Hash::make('1234'),
                'whatsapp_notifications' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }

    public function down(): void
    {
        DB::table('fleet_customers')->truncate();
    }
};
