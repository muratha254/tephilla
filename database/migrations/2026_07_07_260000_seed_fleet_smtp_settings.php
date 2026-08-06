<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('fleet_settings')) {
            return;
        }

        $row = DB::table('fleet_settings')->first();
        if (! $row) {
            return;
        }

        $options = json_decode($row->options ?? '{}', true) ?: [];

        if (! empty($options['smtp'])) {
            return;
        }

        $options['smtp'] = [
            'host' => 'smtp.gmail.com',
            'smtp_auth' => 'True',
            'username' => 'smtp112024@gmail.com',
            'password' => Crypt::encryptString('changeme123'),
            'smtp_secure' => 'SSL',
            'port' => '465',
        ];

        DB::table('fleet_settings')
            ->where('id', $row->id)
            ->update([
                'options' => json_encode($options),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('fleet_settings')) {
            return;
        }

        $row = DB::table('fleet_settings')->first();
        if (! $row) {
            return;
        }

        $options = json_decode($row->options ?? '{}', true) ?: [];
        unset($options['smtp']);

        DB::table('fleet_settings')
            ->where('id', $row->id)
            ->update([
                'options' => json_encode($options),
                'updated_at' => now(),
            ]);
    }
};
