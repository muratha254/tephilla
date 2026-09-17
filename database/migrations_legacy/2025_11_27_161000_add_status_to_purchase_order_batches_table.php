<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_order_batches', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_order_batches', 'status')) {
                $table->enum('status', ['pending', 'partially_received', 'completed'])
                    ->default('pending')
                    ->after('notes');
            }

            if (!Schema::hasColumn('purchase_order_batches', 'closed_at')) {
                $table->timestamp('closed_at')->nullable()->after('status');
            }
        });

        Schema::table('purchase_order_batch_items', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_order_batch_items', 'received_quantity')) {
                $table->unsignedInteger('received_quantity')->default(0)->after('quantity');
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchase_order_batch_items', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_order_batch_items', 'received_quantity')) {
                $table->dropColumn('received_quantity');
            }
        });

        Schema::table('purchase_order_batches', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_order_batches', 'closed_at')) {
                $table->dropColumn('closed_at');
            }
            if (Schema::hasColumn('purchase_order_batches', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};






