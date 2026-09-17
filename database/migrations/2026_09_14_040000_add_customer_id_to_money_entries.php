<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCustomerIdToMoneyEntries extends Migration
{
    public function up()
    {
        Schema::table('money_entries', function (Blueprint $table) {
            if (! Schema::hasColumn('money_entries', 'customer_id')) {
                $table->foreignId('customer_id')->nullable()->after('supplier_id')->constrained()->nullOnDelete();
            }
        });
    }

    public function down()
    {
        Schema::table('money_entries', function (Blueprint $table) {
            if (Schema::hasColumn('money_entries', 'customer_id')) {
                $table->dropConstrainedForeignId('customer_id');
            }
        });
    }
}
