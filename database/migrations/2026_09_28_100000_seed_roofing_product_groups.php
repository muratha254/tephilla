<?php

use App\Services\CompanyProvisioner;
use Illuminate\Database\Migrations\Migration;

class SeedRoofingProductGroups extends Migration
{
    public function up()
    {
        app(CompanyProvisioner::class)->ensureRoofingCatalogs();
    }

    public function down()
    {
        // Categories may already be used by products, so they are left in place.
    }
}
