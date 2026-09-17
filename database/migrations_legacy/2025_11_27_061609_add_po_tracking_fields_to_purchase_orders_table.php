<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddPoTrackingFieldsToPurchaseOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_orders', 'po_number')) {
                $table->string('po_number', 50)->nullable()->after('id');
            }

            if (!Schema::hasColumn('purchase_orders', 'received_quantity')) {
                $table->unsignedInteger('received_quantity')->default(0)->after('quantity');
            }
        });

        if (Schema::hasColumn('purchase_orders', 'status')) {
            DB::statement("ALTER TABLE purchase_orders MODIFY COLUMN status ENUM('pending','partially_received','completed') DEFAULT 'pending'");
        }

        $orders = DB::table('purchase_orders')
            ->select('id', 'order_date', 'po_number')
            ->orderBy('id')
            ->get();

        foreach ($orders as $order) {
            if ($order->po_number) {
                continue;
            }

            $date = $order->order_date ?? now();
            $poNumber = sprintf('PO-%s-%04d', date('Ymd', strtotime($date)), $order->id);

            DB::table('purchase_orders')
                ->where('id', $order->id)
                ->update(['po_number' => $poNumber]);
        }

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->unique('po_number');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_orders', 'po_number')) {
                $table->dropUnique('purchase_orders_po_number_unique');
                $table->dropColumn('po_number');
            }

            if (Schema::hasColumn('purchase_orders', 'received_quantity')) {
                $table->dropColumn('received_quantity');
            }
        });

        if (Schema::hasColumn('purchase_orders', 'status')) {
            DB::statement("ALTER TABLE purchase_orders MODIFY COLUMN status ENUM('pending','ordered','received') DEFAULT 'pending'");
        }
    }
}
