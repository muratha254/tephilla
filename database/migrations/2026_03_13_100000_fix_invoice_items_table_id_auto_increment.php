<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class FixInvoiceItemsTableIdAutoIncrement extends Migration
{
    /**
     * Run the migrations.
     * Fix: Field 'id' doesn't have a default value when inserting multiple consignment lines.
     */
    public function up()
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        $column = DB::selectOne("SHOW COLUMNS FROM `invoice_items` WHERE Field = 'id'");
        if ($column && strpos($column->Extra, 'auto_increment') === false) {
            DB::statement('ALTER TABLE `invoice_items` MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
        }
    }

    public function down()
    {
        // Not reversible safely
    }
}




