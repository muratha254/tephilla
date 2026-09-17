<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('setting', function (Blueprint $table) {
            if (! Schema::hasColumn('setting', 'powered_by')) {
                $table->string('powered_by')->nullable()->after('path_kartu_member');
            }
            if (! Schema::hasColumn('setting', 'powered_by_website')) {
                $table->string('powered_by_website')->nullable()->after('powered_by');
            }
            if (! Schema::hasColumn('setting', 'powered_by_email')) {
                $table->string('powered_by_email')->nullable()->after('powered_by_website');
            }
        });

        DB::table('setting')->where('id_setting', 1)->update([
            'powered_by' => 'Powered By REOPRIME SOLUTIONS LTD 254722555849',
            'powered_by_website' => 'www.reoprime.com',
            'powered_by_email' => 'info@gmail.com',
        ]);
    }

    public function down(): void
    {
        Schema::table('setting', function (Blueprint $table) {
            foreach (['powered_by', 'powered_by_website', 'powered_by_email'] as $column) {
                if (Schema::hasColumn('setting', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
