<?php

use App\Models\Company;
use App\Models\Unit;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColoursFoldingAndBranchUsers extends Migration
{
    public function up()
    {
        Schema::create('colours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'name']);
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->foreignId('colour_id')->nullable()->after('product_id')->constrained('colours')->nullOnDelete();
        });

        Schema::create('foldings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('product_variant_id')->default(0);
            $table->foreignId('colour_id')->nullable()->constrained('colours')->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('employee_name');
            $table->date('folded_on');
            $table->decimal('quantity', 15, 4);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'folded_on']);
            $table->index(['branch_id', 'product_id']);
        });

        Schema::create('branch_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'branch_id']);
        });

        $starter = ['Black', 'Coffee Brown', 'Maroon Red', 'Black Red'];

        Company::query()->orderBy('id')->each(function (Company $company) use ($starter) {
            foreach ($starter as $name) {
                \App\Models\Colour::query()->firstOrCreate(
                    ['company_id' => $company->id, 'name' => $name],
                    ['is_active' => true]
                );
            }

            Unit::query()->firstOrCreate(
                ['company_id' => $company->id, 'short_name' => 'KG'],
                ['name' => 'Kilogram', 'multiplier' => 1, 'is_active' => true]
            );
        });

    }

    public function down()
    {
        Schema::dropIfExists('branch_user');
        Schema::dropIfExists('foldings');
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('colour_id');
        });
        Schema::dropIfExists('colours');
    }
}
