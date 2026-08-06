<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HardenModulePermissionDefaults extends Migration
{
    public function up()
    {
        // Set all module flags to false by default for existing non-admin users
        DB::table('users')
            ->where('role', '!=', 'admin')
            ->update([
                'inv_create' => false, 'inv_read' => false, 'inv_update' => false, 'inv_delete' => false,
                'sal_create' => false, 'sal_read' => false, 'sal_update' => false, 'sal_delete' => false,
                'exp_create' => false, 'exp_read' => false, 'exp_update' => false, 'exp_delete' => false,
                'rep_create' => false, 'rep_read' => false, 'rep_update' => false, 'rep_delete' => false,
            ]);
    }

    public function down()
    {
        // No-op: cannot reliably restore previous values
    }
}
















