<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class FixJobsTableIdAutoIncrement extends Migration
{
    /**
     * Run the migrations.
     * Fix: Field 'id' doesn't have a default value - ensure jobs.id is AUTO_INCREMENT.
     *
     * @return void
     */
    public function up()
    {
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE `jobs` MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Reverting would remove AUTO_INCREMENT; leave as no-op to avoid breaking queue
    }
}





