<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCompanyProfileFieldsToCompaniesTable extends Migration
{
    public function up()
    {
        Schema::table('companies', function (Blueprint $table) {
            if (! Schema::hasColumn('companies', 'phone_alt')) {
                $table->string('phone_alt')->nullable()->after('phone');
            }
            if (! Schema::hasColumn('companies', 'state')) {
                $table->string('state')->nullable()->after('city');
            }
            if (! Schema::hasColumn('companies', 'postcode')) {
                $table->string('postcode', 40)->nullable()->after('state');
            }
            if (! Schema::hasColumn('companies', 'employer_code')) {
                $table->string('employer_code', 64)->nullable()->after('vat_number');
            }
            if (! Schema::hasColumn('companies', 'bank_details')) {
                $table->text('bank_details')->nullable()->after('employer_code');
            }
            if (! Schema::hasColumn('companies', 'quotation_terms')) {
                $table->text('quotation_terms')->nullable()->after('bank_details');
            }
        });
    }

    public function down()
    {
        Schema::table('companies', function (Blueprint $table) {
            foreach (['phone_alt', 'state', 'postcode', 'employer_code', 'bank_details', 'quotation_terms'] as $col) {
                if (Schema::hasColumn('companies', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
}
