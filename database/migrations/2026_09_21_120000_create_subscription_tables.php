<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateSubscriptionTables extends Migration
{
    public function up()
    {
        if (! Schema::hasColumn('companies', 'owner_name')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->string('owner_name')->nullable()->after('name');
            });
        }

        $this->makeRolesCompanyNullable();

        if (! Schema::hasTable('subscription_plans')) {
            Schema::create('subscription_plans', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->decimal('price', 12, 2)->default(0);
                $table->string('billing_period', 32);
                $table->unsignedInteger('duration_days')->nullable();
                $table->unsignedInteger('max_users')->nullable();
                $table->unsignedInteger('max_branches')->nullable();
                $table->json('features')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('subscriptions')) {
            Schema::create('subscriptions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->unique()->constrained()->cascadeOnDelete();
                $table->foreignId('subscription_plan_id')->constrained()->restrictOnDelete();
                $table->string('status', 32)->default('trial');
                $table->date('starts_at');
                $table->date('expires_at');
                $table->timestamp('trial_ends_at')->nullable();
                $table->timestamp('suspended_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->index(['status', 'expires_at']);
                $table->index('expires_at');
            });
        }

        if (! Schema::hasTable('subscription_history')) {
            Schema::create('subscription_history', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('action', 64);
                $table->unsignedBigInteger('previous_plan_id')->nullable();
                $table->unsignedBigInteger('new_plan_id')->nullable();
                $table->date('previous_expires_at')->nullable();
                $table->date('new_expires_at')->nullable();
                $table->string('previous_status', 32)->nullable();
                $table->string('new_status', 32)->nullable();
                $table->text('notes')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->timestamps();
                $table->index(['company_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('subscription_payments')) {
            Schema::create('subscription_payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->decimal('amount', 12, 2);
                $table->string('method', 32)->default('other');
                $table->string('reference')->nullable();
                $table->date('paid_at');
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->index(['company_id', 'paid_at']);
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('subscription_payments');
        Schema::dropIfExists('subscription_history');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('subscription_plans');

        Schema::table('roles', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropUnique(['company_id', 'name']);
        });
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE roles MODIFY company_id BIGINT UNSIGNED NOT NULL');
        } else {
            Schema::table('roles', function (Blueprint $table) {
                $table->unsignedBigInteger('company_id')->nullable(false)->change();
            });
        }
        Schema::table('roles', function (Blueprint $table) {
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->unique(['company_id', 'name']);
        });

        if (Schema::hasColumn('companies', 'owner_name')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->dropColumn('owner_name');
            });
        }
    }

    private function makeRolesCompanyNullable(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        $hasUnique = false;
        try {
            $indexes = Schema::getConnection()->getDoctrineSchemaManager()->listTableIndexes('roles');
            $hasUnique = isset($indexes['roles_company_id_name_unique']);
        } catch (\Throwable $e) {
            $hasUnique = true;
        }

        try {
            Schema::table('roles', function (Blueprint $table) {
                $table->dropForeign(['company_id']);
            });
        } catch (\Throwable $e) {
            // Foreign key already removed.
        }

        if ($hasUnique) {
            try {
                Schema::table('roles', function (Blueprint $table) {
                    $table->dropUnique(['company_id', 'name']);
                });
            } catch (\Throwable $e) {
                // Unique index already removed.
            }
        }

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE roles MODIFY company_id BIGINT UNSIGNED NULL');
        } else {
            Schema::table('roles', function (Blueprint $table) {
                $table->unsignedBigInteger('company_id')->nullable()->change();
            });
        }

        Schema::table('roles', function (Blueprint $table) {
            $table->foreign('company_id')->references('id')->on('companies')->nullOnDelete();
            $table->unique(['company_id', 'name']);
        });
    }
}
