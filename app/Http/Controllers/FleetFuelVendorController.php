<?php

namespace App\Http\Controllers;

use App\Models\FleetFuelVendor;
use Illuminate\Http\Request;

class FleetFuelVendorController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));

        $vendors = FleetFuelVendor::query()
            ->when($search !== '', fn ($query) => $query->where('name', 'like', '%' . $search . '%'))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('fleet.fuel-vendors.index', array_merge($this->sharedViewData(), [
            'activeMenu' => 'fuel-vendor',
            'openMenu' => 'fuel',
            'vendors' => $vendors,
            'search' => $search,
        ]));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150|unique:fleet_fuel_vendors,name',
        ]);

        FleetFuelVendor::create($validated);

        return redirect()
            ->route('fuel-vendors.index')
            ->with('success', 'Fuel vendor added successfully.');
    }

    public function destroy(FleetFuelVendor $fuelVendor)
    {
        $fuelVendor->delete();

        return redirect()
            ->route('fuel-vendors.index')
            ->with('success', 'Fuel vendor deleted.');
    }

    public function exportPdf(Request $request)
    {
        $search = trim((string) $request->query('search', ''));

        $vendors = FleetFuelVendor::query()
            ->when($search !== '', fn ($query) => $query->where('name', 'like', '%' . $search . '%'))
            ->orderBy('name')
            ->get();

        return view('fleet.fuel-vendors.export-pdf', [
            'vendors' => $vendors,
            'companyName' => 'One Translines Pvt Ltd',
        ]);
    }

    private function sharedViewData(): array
    {
        return [
            'companyName' => 'One Translines Pvt Ltd',
            'notificationCount' => 11,
        ];
    }
}
