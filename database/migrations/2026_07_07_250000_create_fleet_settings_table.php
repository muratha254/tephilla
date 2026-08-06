<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fleet_settings', function (Blueprint $table) {
            $table->id();
            $table->string('company_name');
            $table->text('address')->nullable();
            $table->string('date_format', 50)->default('d/m/Y');
            $table->string('auto_backup', 20)->default('Enabled');
            $table->string('phone_number', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('menu_position', 50)->default('Vertical (Sidebar)');
            $table->string('booking_id_prefix', 50)->nullable();
            $table->string('default_timezone', 100)->default('Asia/Kolkata');
            $table->string('admin_primary_color', 20)->default('#3498DB');
            $table->string('admin_secondary_color', 20)->default('#2980B9');
            $table->string('sidebar_gradient_start', 20)->default('#2C3E50');
            $table->string('sidebar_gradient_end', 20)->default('#1A252F');
            $table->string('sidebar_text_color', 20)->default('#ECF0F1');
            $table->string('logo_path')->nullable();
            $table->json('options')->nullable();
            $table->timestamps();
        });

        DB::table('fleet_settings')->insert([
            'company_name' => 'One Translines Pvt Ltd',
            'address' => '3/1, Siddana Lane, Rammannapet, Bangalore, India',
            'date_format' => 'd/m/Y',
            'auto_backup' => 'Enabled',
            'phone_number' => '+91 97987977979',
            'email' => 'connect@transline.com',
            'menu_position' => 'Vertical (Sidebar)',
            'booking_id_prefix' => 'OT-2026-',
            'default_timezone' => 'Asia/Kolkata',
            'admin_primary_color' => '#3498DB',
            'admin_secondary_color' => '#2980B9',
            'sidebar_gradient_start' => '#2C3E50',
            'sidebar_gradient_end' => '#1A252F',
            'sidebar_text_color' => '#ECF0F1',
            'options' => json_encode([
                'display' => [
                    'records_per_page' => '25',
                    'currency_symbol' => 'KSh',
                ],
                'invoice' => [
                    'invoice_prefix' => 'INV-',
                    'invoice_footer' => 'Thank you for your business.',
                ],
                'map' => [
                    'default_latitude' => '-1.286389',
                    'default_longitude' => '36.817223',
                    'map_provider' => 'OpenStreetMap',
                ],
                'mobile' => [
                    'app_name' => 'One Translines Fleet',
                    'android_url' => '',
                    'ios_url' => '',
                ],
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_settings');
    }
};
