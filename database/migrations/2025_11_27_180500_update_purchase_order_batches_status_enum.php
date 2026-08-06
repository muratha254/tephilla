<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE purchase_order_batches MODIFY COLUMN status ENUM('pending','partially_received','completed','cancelled','rejected') DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE purchase_order_batches MODIFY COLUMN status ENUM('pending','partially_received','completed','cancelled') DEFAULT 'pending'");
    }
};




