<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddInvoiceNoteToCompaniesTable extends Migration
{
    public function up()
    {
        Schema::table('companies', function (Blueprint $table) {
            if (! Schema::hasColumn('companies', 'invoice_note')) {
                $table->text('invoice_note')->nullable()->after('quotation_terms');
            }
        });
    }

    public function down()
    {
        Schema::table('companies', function (Blueprint $table) {
            if (Schema::hasColumn('companies', 'invoice_note')) {
                $table->dropColumn('invoice_note');
            }
        });
    }
}
