<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSupplierIdToMoneyEntries extends Migration
{
    public function up()
    {
        Schema::table('money_entries', function (Blueprint $table) {
            if (! Schema::hasColumn('money_entries', 'supplier_id')) {
                $table->foreignId('supplier_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            }
        });
    }

    public function down()
    {
        Schema::table('money_entries', function (Blueprint $table) {
            if (Schema::hasColumn('money_entries', 'supplier_id')) {
                $table->dropConstrainedForeignId('supplier_id');
            }
        });
    }
}
