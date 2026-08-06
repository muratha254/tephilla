<?php

namespace App\Http\Controllers;

use App\Models\FleetVehicleVendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use PDF;

class FleetVehicleVendorController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));

        $vendors = $this->filteredVendorsQuery($search)
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('fleet.vendors.index', array_merge($this->sharedViewData(), [
            'activeMenu' => 'vendor-list',
            'openMenu' => 'vendors',
            'vendors' => $vendors,
            'search' => $search,
        ]));
    }

    public function exportPdf(Request $request)
    {
        $search = trim((string) $request->query('search', ''));

        $vendors = $this->filteredVendorsQuery($search)
            ->orderBy('company')
            ->get();

        $pdf = PDF::loadView('fleet.vendors.pdf', array_merge(fleet_company_profile(), [
            'vendors' => $vendors,
            'search' => $search,
            'generatedAt' => now()->format('Y-m-d H:i'),
        ]))->setPaper('a4', 'landscape');

        $filename = 'vehicle-vendors-' . now()->format('Y-m-d') . '.pdf';

        return $pdf->download($filename);
    }

    public function create()
    {
        return view('fleet.vendors.create', array_merge($this->sharedViewData(), [
            'activeMenu' => 'vendor-add',
            'openMenu' => 'vendors',
            'vendor' => null,
        ]));
    }

    public function store(Request $request)
    {
        $validated = $this->validateVendor($request);

        if ($request->hasFile('contract_doc')) {
            $validated['contract_doc'] = $request->file('contract_doc')->store('vendor-contracts', 'public');
        }

        FleetVehicleVendor::create($validated);

        return redirect()
            ->route('vehicle-vendors.index')
            ->with('success', 'Vehicle vendor created successfully.');
    }

    public function edit(FleetVehicleVendor $vehicleVendor)
    {
        return view('fleet.vendors.edit', array_merge($this->sharedViewData(), [
            'activeMenu' => 'vendor-list',
            'openMenu' => 'vendors',
            'vendor' => $vehicleVendor,
        ]));
    }

    public function update(Request $request, FleetVehicleVendor $vehicleVendor)
    {
        $validated = $this->validateVendor($request, $vehicleVendor);

        if ($request->hasFile('contract_doc')) {
            if ($vehicleVendor->contract_doc) {
                Storage::disk('public')->delete($vehicleVendor->contract_doc);
            }
            $validated['contract_doc'] = $request->file('contract_doc')->store('vendor-contracts', 'public');
        }

        $vehicleVendor->update($validated);

        return redirect()
            ->route('vehicle-vendors.index')
            ->with('success', 'Vehicle vendor updated successfully.');
    }

    public function destroy(FleetVehicleVendor $vehicleVendor)
    {
        if ($vehicleVendor->contract_doc) {
            Storage::disk('public')->delete($vehicleVendor->contract_doc);
        }

        $vehicleVendor->delete();

        return redirect()
            ->route('vehicle-vendors.index')
            ->with('success', 'Vehicle vendor deleted successfully.');
    }

    private function validateVendor(Request $request, ?FleetVehicleVendor $vendor = null): array
    {
        $rules = [
            'company' => 'required|string|max:150',
            'contact_person' => 'required|string|max:100',
            'mobile' => 'required|string|max:30',
            'contract_date' => 'required|date',
            'status' => 'required|in:Active,Inactive',
            'address' => 'required|string|max:255',
        ];

        $rules['contract_doc'] = $vendor
            ? 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048'
            : 'required|file|mimes:pdf,jpg,jpeg,png|max:2048';

        $validated = $request->validate($rules);

        $validated['is_active'] = $validated['status'] === 'Active';
        unset($validated['status']);

        return $validated;
    }

    private function sharedViewData(): array
    {
        return [
            'companyName' => 'One Translines Pvt Ltd',
            'notificationCount' => 11,
        ];
    }

    private function filteredVendorsQuery(string $search)
    {
        return FleetVehicleVendor::query()->when($search !== '', function ($query) use ($search) {
            $query->where(function ($inner) use ($search) {
                $inner->where('company', 'like', "%{$search}%")
                    ->orWhere('contact_person', 'like', "%{$search}%")
                    ->orWhere('mobile', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%");
            });
        });
    }
}
