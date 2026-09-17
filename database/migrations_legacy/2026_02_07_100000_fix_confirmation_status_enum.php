<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FixConfirmationStatusEnum extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Check if column exists
        if (!Schema::hasColumn('penjualan', 'confirmation_status')) {
            $after = Schema::hasColumn('penjualan', 'status') ? ' AFTER status' : '';
            DB::statement("ALTER TABLE penjualan ADD COLUMN confirmation_status ENUM('pending', 'confirmed', 'defect') NOT NULL DEFAULT 'pending'{$after}");
        } else {
            // First, update any invalid or NULL values to 'pending'
            DB::statement("UPDATE penjualan SET confirmation_status = 'pending' WHERE confirmation_status IS NULL OR confirmation_status NOT IN ('pending', 'confirmed', 'defect')");
            
            // Now modify the column to ensure proper ENUM definition
            // Temporarily allow NULL to avoid issues during modification
            DB::statement("ALTER TABLE penjualan MODIFY COLUMN confirmation_status ENUM('pending', 'confirmed', 'defect') NULL DEFAULT 'pending'");
            
            // Update any NULL values to 'pending'
            DB::statement("UPDATE penjualan SET confirmation_status = 'pending' WHERE confirmation_status IS NULL");
            
            // Finally, set it back to NOT NULL
            DB::statement("ALTER TABLE penjualan MODIFY COLUMN confirmation_status ENUM('pending', 'confirmed', 'defect') NOT NULL DEFAULT 'pending'");
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasColumn('penjualan', 'confirmation_status')) {
            Schema::table('penjualan', function ($table) {
                $table->dropColumn('confirmation_status');
            });
        }
    }
}

