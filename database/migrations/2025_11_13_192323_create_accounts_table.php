<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Account;

class CreateAccountsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // Cash, Mpesa, Card
            $table->string('type')->default('payment'); // payment, bank, etc.
            $table->decimal('balance', 15, 2)->default(0);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Create default accounts
        $accounts = [
            [
                'name' => 'Cash',
                'type' => 'payment',
                'balance' => 0,
                'description' => 'Cash payment account',
                'is_active' => true
            ],
            [
                'name' => 'Mpesa',
                'type' => 'payment',
                'balance' => 0,
                'description' => 'Mpesa mobile money account',
                'is_active' => true
            ],
            [
                'name' => 'Card',
                'type' => 'payment',
                'balance' => 0,
                'description' => 'Card payment account',
                'is_active' => true
            ]
        ];

        foreach ($accounts as $account) {
            Account::create($account);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('accounts');
    }
}
