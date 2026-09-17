<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add 'edit_initiated' to penjualan.status ENUM so Initiate edit works.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('penjualan', 'status')) {
            return;
        }

        // Add 'edit_initiated' to the status enum (MySQL/MariaDB)
        DB::statement("ALTER TABLE penjualan MODIFY COLUMN status ENUM('active','suspended','completed','edit_initiated') NOT NULL DEFAULT 'active'");
    }

    /**
     * Reverse: remove 'edit_initiated' from enum.
     */
    public function down(): void
    {
        if (!Schema::hasColumn('penjualan', 'status')) {
            return;
        }

        // Revert to original enum (convert any edit_initiated rows to active first)
        DB::statement("UPDATE penjualan SET status = 'active' WHERE status = 'edit_initiated'");
        DB::statement("ALTER TABLE penjualan MODIFY COLUMN status ENUM('active','suspended','completed') NOT NULL DEFAULT 'active'");
    }
};
