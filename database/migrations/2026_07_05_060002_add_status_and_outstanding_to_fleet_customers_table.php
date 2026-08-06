<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fleet_customers', function (Blueprint $table) {
            if (! Schema::hasColumn('fleet_customers', 'status')) {
                $table->string('status', 20)->default('Active')->after('whatsapp_notifications');
            }

            if (! Schema::hasColumn('fleet_customers', 'outstanding_payment')) {
                $table->decimal('outstanding_payment', 12, 2)->default(0)->after('status');
            }
        });

        if (Schema::hasColumn('fleet_customers', 'status')) {
            DB::table('fleet_customers')->whereNull('status')->update(['status' => 'Active']);
        }

        if (Schema::hasColumn('fleet_customers', 'outstanding_payment')) {
            DB::table('fleet_customers')->whereNull('outstanding_payment')->update(['outstanding_payment' => 0]);
        }
    }

    public function down(): void
    {
        Schema::table('fleet_customers', function (Blueprint $table) {
            if (Schema::hasColumn('fleet_customers', 'outstanding_payment')) {
                $table->dropColumn('outstanding_payment');
            }

            if (Schema::hasColumn('fleet_customers', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};
