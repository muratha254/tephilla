<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PayrollSetting;
use Illuminate\Support\Facades\Validator;

class PayrollSettingsController extends Controller
{
    public function index()
    {
        $settings = PayrollSetting::getSettings();
        return view('payroll.settings', compact('settings'));
    }

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'paye_band1_min' => 'required|numeric|min:0',
            'paye_band1_max' => 'required|numeric|min:0',
            'paye_band1_rate' => 'required|numeric|min:0|max:100',
            'paye_band2_min' => 'required|numeric|min:0',
            'paye_band2_max' => 'required|numeric|min:0',
            'paye_band2_rate' => 'required|numeric|min:0|max:100',
            'paye_band3_min' => 'required|numeric|min:0',
            'paye_band3_rate' => 'required|numeric|min:0|max:100',
            'personal_relief' => 'required|numeric|min:0',
            'nssf_tier1_limit' => 'required|numeric|min:0',
            'nssf_tier2_limit' => 'required|numeric|min:0',
            'nssf_rate' => 'required|numeric|min:0|max:100',
            'nhif_bands' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors(), 'message' => 'Please check your input'], 422);
        }

        $settings = PayrollSetting::getSettings();
        
        // Update PAYE settings
        $settings->paye_band1_min = $request->paye_band1_min;
        $settings->paye_band1_max = $request->paye_band1_max;
        $settings->paye_band1_rate = $request->paye_band1_rate;
        $settings->paye_band2_min = $request->paye_band2_min;
        $settings->paye_band2_max = $request->paye_band2_max;
        $settings->paye_band2_rate = $request->paye_band2_rate;
        $settings->paye_band3_min = $request->paye_band3_min;
        $settings->paye_band3_rate = $request->paye_band3_rate;
        $settings->personal_relief = $request->personal_relief;
        
        // Update NSSF settings
        $settings->nssf_tier1_limit = $request->nssf_tier1_limit;
        $settings->nssf_tier2_limit = $request->nssf_tier2_limit;
        $settings->nssf_rate = $request->nssf_rate;
        
        // Update SHA (Social Health Authority) bands
        $settings->nhif_bands = $request->nhif_bands;
        
        $settings->save();

        return response()->json(['data' => $settings, 'message' => 'Payroll settings updated successfully']);
    }
}
