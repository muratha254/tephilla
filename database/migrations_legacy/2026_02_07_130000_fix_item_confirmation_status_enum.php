<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FixItemConfirmationStatusEnum extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Check if column exists
        if (!Schema::hasColumn('penjualan_detail', 'item_confirmation_status')) {
            // Create the column using raw SQL to ensure proper ENUM definition
            DB::statement("ALTER TABLE penjualan_detail ADD COLUMN item_confirmation_status ENUM('pending', 'confirmed', 'defect') NOT NULL DEFAULT 'pending' AFTER subtotal");
        } else {
            // First, update any invalid or NULL values to 'pending'
            DB::statement("UPDATE penjualan_detail SET item_confirmation_status = 'pending' WHERE item_confirmation_status IS NULL OR item_confirmation_status NOT IN ('pending', 'confirmed', 'defect')");
            
            // Now modify the column to ensure proper ENUM definition
            // Temporarily allow NULL to avoid issues during modification
            DB::statement("ALTER TABLE penjualan_detail MODIFY COLUMN item_confirmation_status ENUM('pending', 'confirmed', 'defect') NULL DEFAULT 'pending'");
            
            // Update any NULL values to 'pending'
            DB::statement("UPDATE penjualan_detail SET item_confirmation_status = 'pending' WHERE item_confirmation_status IS NULL");
            
            // Finally, set it back to NOT NULL
            DB::statement("ALTER TABLE penjualan_detail MODIFY COLUMN item_confirmation_status ENUM('pending', 'confirmed', 'defect') NOT NULL DEFAULT 'pending'");
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasColumn('penjualan_detail', 'item_confirmation_status')) {
            Schema::table('penjualan_detail', function ($table) {
                $table->dropColumn('item_confirmation_status');
            });
        }
    }
}





