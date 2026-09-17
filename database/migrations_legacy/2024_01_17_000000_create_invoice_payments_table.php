<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInvoicePaymentsTable extends Migration
{
    public function up()
    {
        // Make this migration idempotent: only create the table if it doesn't exist yet.
        if (! Schema::hasTable('invoice_payments')) {
            Schema::create('invoice_payments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('invoice_id');
                $table->unsignedBigInteger('payment_id');
                $table->unsignedBigInteger('supplier_id');
                $table->decimal('amount_paid', 15, 2);
                $table->decimal('remaining_balance', 15, 2);
                $table->string('payment_reference');
                $table->enum('payment_status', ['Pending', 'Completed', 'Failed', 'Reversed']);
                $table->text('notes')->nullable();
                $table->timestamp('payment_date');
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('invoice_payments');
    }
}
