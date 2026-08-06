<?php

namespace App\Http\Controllers;

use App\Models\FleetTripType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FleetTripLookupController extends Controller
{
    public function storeTripTypeQuick(Request $request)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:50',
                Rule::unique('fleet_trip_types', 'name'),
            ],
        ]);

        $tripType = FleetTripType::create([
            'name' => trim($validated['name']),
        ]);

        return response()->json([
            'message' => 'Trip type added successfully.',
            'trip_type' => [
                'id' => $tripType->id,
                'name' => $tripType->name,
            ],
        ]);
    }
}
