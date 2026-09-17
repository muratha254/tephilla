<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSupplierAuditLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('supplier_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('id_supplier');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action', 64);
            $table->json('changes');
            $table->string('source_file')->nullable();
            $table->timestamps();

            $table->foreign('id_supplier')->references('id_supplier')->on('supplier')->onDelete('cascade');
            $table->index(['id_supplier', 'created_at']);
            $table->index('action');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('supplier_audit_logs');
    }
}
