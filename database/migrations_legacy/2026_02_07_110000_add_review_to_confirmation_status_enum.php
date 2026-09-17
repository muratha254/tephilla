<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddReviewToConfirmationStatusEnum extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasColumn('penjualan', 'confirmation_status')) {
            // Add 'review' to the confirmation_status enum
            DB::statement("ALTER TABLE penjualan MODIFY COLUMN confirmation_status ENUM('pending', 'confirmed', 'defect', 'review') NOT NULL DEFAULT 'pending'");
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
            // Remove 'review' from enum (convert any review rows to pending first)
            DB::statement("UPDATE penjualan SET confirmation_status = 'pending' WHERE confirmation_status = 'review'");
            DB::statement("ALTER TABLE penjualan MODIFY COLUMN confirmation_status ENUM('pending', 'confirmed', 'defect') NOT NULL DEFAULT 'pending'");
        }
    }
}





