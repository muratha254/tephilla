<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ExpandCustomerProfiles extends Migration
{
    public function up()
    {
        Schema::table('customer_categories', function (Blueprint $table) {
            if (! Schema::hasColumn('customer_categories', 'description')) {
                $table->string('description', 500)->nullable()->after('discount_percent');
            }
        });

        Schema::table('customers', function (Blueprint $table) {
            if (! Schema::hasColumn('customers', 'branch_id')) {
                $table->foreignId('branch_id')->nullable()->after('company_id')->constrained()->nullOnDelete();
            }
            if (! Schema::hasColumn('customers', 'mobile')) {
                $table->string('mobile', 64)->nullable()->after('phone');
            }
            if (! Schema::hasColumn('customers', 'national_id')) {
                $table->string('national_id', 64)->nullable()->after('email');
            }
            if (! Schema::hasColumn('customers', 'county')) {
                $table->string('county', 64)->nullable()->after('address');
            }
            if (! Schema::hasColumn('customers', 'estate')) {
                $table->string('estate', 128)->nullable()->after('county');
            }
            if (! Schema::hasColumn('customers', 'postcode')) {
                $table->string('postcode', 32)->nullable()->after('estate');
            }
            if (! Schema::hasColumn('customers', 'shop_image_path')) {
                $table->string('shop_image_path')->nullable()->after('postcode');
            }
            if (! Schema::hasColumn('customers', 'migration_account')) {
                $table->string('migration_account', 64)->nullable()->after('shop_image_path');
            }
            if (! Schema::hasColumn('customers', 'assigned_to')) {
                $table->foreignId('assigned_to')->nullable()->after('migration_account')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('customers', 'loyalty_points')) {
                $table->decimal('loyalty_points', 15, 2)->default(0)->after('credit_limit');
            }
        });
    }

    public function down()
    {
        Schema::table('customers', function (Blueprint $table) {
            if (Schema::hasColumn('customers', 'assigned_to')) {
                $table->dropConstrainedForeignId('assigned_to');
            }
            if (Schema::hasColumn('customers', 'branch_id')) {
                $table->dropConstrainedForeignId('branch_id');
            }
            foreach ([
                'mobile', 'national_id', 'county', 'estate', 'postcode',
                'shop_image_path', 'migration_account', 'loyalty_points',
            ] as $column) {
                if (Schema::hasColumn('customers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('customer_categories', function (Blueprint $table) {
            if (Schema::hasColumn('customer_categories', 'description')) {
                $table->dropColumn('description');
            }
        });
    }
}
