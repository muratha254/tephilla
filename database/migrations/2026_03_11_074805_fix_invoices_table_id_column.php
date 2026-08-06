<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class FixInvoicesTableIdColumn extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Check if id column exists and is not already auto-incrementing
        $column = DB::selectOne("SHOW COLUMNS FROM `invoices` WHERE Field = 'id'");
        if ($column && strpos($column->Extra, 'auto_increment') === false) {
            // First, check if there's an existing primary key
            $keys = DB::select("SHOW KEYS FROM `invoices` WHERE Key_name = 'PRIMARY'");
            
            // If no primary key exists, add it first, then make it auto-increment
            if (empty($keys)) {
                // Add primary key constraint
                DB::statement('ALTER TABLE `invoices` ADD PRIMARY KEY (`id`)');
            }
            
            // Now modify the id column to be auto-incrementing
            DB::statement('ALTER TABLE `invoices` MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // This migration is not reversible safely
    }
}
