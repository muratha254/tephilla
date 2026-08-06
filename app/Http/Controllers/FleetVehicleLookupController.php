<?php

namespace App\Http\Controllers;

use App\Models\FleetVehicleManufacturer;
use App\Models\FleetVehicleModel;
use App\Models\FleetVehicleType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FleetVehicleLookupController extends Controller
{
    public function storeManufacturerQuick(Request $request)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('fleet_vehicle_manufacturers', 'name'),
            ],
        ]);

        $manufacturer = FleetVehicleManufacturer::create([
            'name' => trim($validated['name']),
        ]);

        return response()->json([
            'message' => 'Manufacturer added successfully.',
            'manufacturer' => [
                'id' => $manufacturer->id,
                'name' => $manufacturer->name,
            ],
        ]);
    }

    public function storeModelQuick(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'fleet_vehicle_manufacturer_id' => 'nullable|exists:fleet_vehicle_manufacturers,id',
        ]);

        $name = trim($validated['name']);
        $manufacturerId = $validated['fleet_vehicle_manufacturer_id'] ?? null;

        $exists = FleetVehicleModel::query()
            ->where('name', $name)
            ->where(function ($query) use ($manufacturerId) {
                if ($manufacturerId === null) {
                    $query->whereNull('fleet_vehicle_manufacturer_id');
                } else {
                    $query->where('fleet_vehicle_manufacturer_id', $manufacturerId);
                }
            })
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'This model already exists for the selected manufacturer.',
                'errors' => [
                    'name' => ['This model already exists for the selected manufacturer.'],
                ],
            ], 422);
        }

        $model = FleetVehicleModel::create([
            'name' => $name,
            'fleet_vehicle_manufacturer_id' => $manufacturerId,
        ]);

        return response()->json([
            'message' => 'Model added successfully.',
            'model' => [
                'id' => $model->id,
                'name' => $model->name,
                'fleet_vehicle_manufacturer_id' => $model->fleet_vehicle_manufacturer_id,
            ],
        ]);
    }

    public function storeVehicleTypeQuick(Request $request)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:50',
                Rule::unique('fleet_vehicle_types', 'name'),
            ],
        ]);

        $vehicleType = FleetVehicleType::create([
            'name' => trim($validated['name']),
        ]);

        return response()->json([
            'message' => 'Vehicle type added successfully.',
            'vehicle_type' => [
                'id' => $vehicleType->id,
                'name' => $vehicleType->name,
            ],
        ]);
    }
}
