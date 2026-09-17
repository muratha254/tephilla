<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('fleet_settings')) {
            return;
        }

        DB::table('fleet_settings')->update([
            'date_format' => 'd/m/Y',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('fleet_settings')) {
            return;
        }

        DB::table('fleet_settings')->update([
            'date_format' => 'Y-m-d H:i',
            'updated_at' => now(),
        ]);
    }
};
