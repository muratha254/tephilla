<?php

use App\Services\CompanyProvisioner;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFoldingOutputsAndVoid extends Migration
{
    public function up()
    {
        Schema::table('foldings', function (Blueprint $table) {
            $table->string('status', 20)->default('confirmed')->after('notes');
            $table->timestamp('voided_at')->nullable()->after('status');
            $table->foreignId('voided_by')->nullable()->after('voided_at')->constrained('users')->nullOnDelete();
            $table->string('void_reason')->nullable()->after('voided_by');
        });

        Schema::create('folding_outputs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('folding_id')->constrained('foldings')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('product_variant_id')->default(0);
            $table->foreignId('colour_id')->nullable()->constrained('colours')->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->decimal('quantity', 15, 4);
            $table->timestamps();
        });

        app(CompanyProvisioner::class)->ensureRoofingCatalogs();
    }

    public function down()
    {
        Schema::dropIfExists('folding_outputs');
        Schema::table('foldings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('voided_by');
            $table->dropColumn(['status', 'voided_at', 'void_reason']);
        });
    }
}
