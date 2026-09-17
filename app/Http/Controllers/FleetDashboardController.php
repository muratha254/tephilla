<?php

namespace App\Http\Controllers;

class FleetDashboardController extends Controller
{
    public function index()
    {
        return view('fleet.dashboard', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'dashboard',
        ]));
    }
}
