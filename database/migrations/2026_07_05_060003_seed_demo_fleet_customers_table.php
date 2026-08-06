<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fleet_customers')) {
            return;
        }

        if (DB::table('fleet_customers')->count() >= 5) {
            return;
        }

        $now = now();
        $existingCount = DB::table('fleet_customers')->count();
        $start = $existingCount + 1;

        for ($i = $start; $i <= 5; $i++) {
            DB::table('fleet_customers')->insert([
                'name' => 'Demo Customer ' . $i,
                'mobile' => '98765432' . str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                'whatsapp' => '98765432' . str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                'whatsapp_same_as_mobile' => true,
                'email' => 'customer' . $i . '@example.com',
                'password' => Hash::make('1234'),
                'address' => 'Chennai, India',
                'whatsapp_notifications' => true,
                'status' => 'Active',
                'outstanding_payment' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('fleet_customers')
            ->where('email', 'like', 'customer%@example.com')
            ->delete();
    }
};
