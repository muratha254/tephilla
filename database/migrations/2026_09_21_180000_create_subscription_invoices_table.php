<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSubscriptionInvoicesTable extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('subscription_invoices')) {
            Schema::create('subscription_invoices', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('invoice_number', 40)->unique();
                $table->string('status', 24)->default('sent');
                $table->decimal('amount', 12, 2);
                $table->string('currency', 8)->default('KES');
                $table->date('due_date');
                $table->timestamp('sent_at')->nullable();
                $table->string('sent_to_email', 190)->nullable();
                $table->date('paid_at')->nullable();
                $table->string('plan_name')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->index(['company_id', 'status']);
            });
        }

        if (Schema::hasTable('subscription_payments') && ! Schema::hasColumn('subscription_payments', 'subscription_invoice_id')) {
            Schema::table('subscription_payments', function (Blueprint $table) {
                $table->foreignId('subscription_invoice_id')->nullable()->after('subscription_id')->constrained('subscription_invoices')->nullOnDelete();
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('subscription_payments', 'subscription_invoice_id')) {
            Schema::table('subscription_payments', function (Blueprint $table) {
                $table->dropConstrainedForeignId('subscription_invoice_id');
            });
        }
        Schema::dropIfExists('subscription_invoices');
    }
}
