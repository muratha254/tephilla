<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SettingController extends Controller
{
    public function index()
    {
        return view('setting.index');
    }

    public function show()
    {
        return Setting::first();
    }

    public function update(Request $request)
    {
        try {
            $setting = Setting::first();
            
            if (!$setting) {
                return response()->json(['message' => 'Setting not found'], 404);
            }
            
            // Log the incoming request data
            \Log::info('Settings update request:', $request->all());
            
            // Update all fields including driver_commission_rate
            $setting->nama_perusahaan = $request->nama_perusahaan;
            $setting->telepon = $request->telepon;
            $setting->alamat = $request->alamat;
            $setting->diskon = $request->diskon;
            $setting->tipe_nota = $request->tipe_nota;
            
            // Update driver commission rate - always set it, even if 0
            $commissionRate = $request->input('driver_commission_rate', 0);
            $commissionRate = is_numeric($commissionRate) ? (float)$commissionRate : 0;
            
            \Log::info('Attempting to set driver_commission_rate to: ' . $commissionRate);
            
            // Try to set via Eloquent first
            try {
                $setting->driver_commission_rate = $commissionRate;
            } catch (\Exception $e) {
                \Log::warning('Could not set via Eloquent: ' . $e->getMessage());
            }
            
            // Also try direct DB update as fallback if column exists
            if (Schema::hasColumn('setting', 'driver_commission_rate')) {
                try {
                    DB::table('setting')
                        ->where('id_setting', $setting->id_setting)
                        ->update(['driver_commission_rate' => $commissionRate]);
                    \Log::info('Updated via direct DB query');
                } catch (\Exception $e) {
                    \Log::error('Direct DB update failed: ' . $e->getMessage());
                }
            } else {
                \Log::error('Column driver_commission_rate does NOT exist in setting table!');
            }

            if ($request->hasFile('path_logo')) {
                $file = $request->file('path_logo');
                $nama = 'logo-' . date('YmdHis') . '.' . $file->getClientOriginalExtension();
                $file->move(public_path('/img'), $nama);

                $setting->path_logo = "/img/$nama";
            }

            if ($request->hasFile('path_kartu_member')) {
                $file = $request->file('path_kartu_member');
                $nama = 'logo-' . date('Y-m-dHis') . '.' . $file->getClientOriginalExtension();
                $file->move(public_path('/img'), $nama);

                $setting->path_kartu_member = "/img/$nama";
            }

            $setting->save();
            
            // Refresh the model to get the latest data from database
            $setting->refresh();
            
            // Log the saved setting to verify
            \Log::info('Settings saved. driver_commission_rate: ' . ($setting->driver_commission_rate ?? 'not set'));
            \Log::info('All setting attributes:', $setting->toArray());

            return response()->json([
                'message' => 'Data saved successfully',
                'driver_commission_rate' => $setting->driver_commission_rate ?? 0,
                'saved_data' => [
                    'driver_commission_rate' => $setting->driver_commission_rate ?? 0
                ]
            ], 200);
        } catch (\Exception $e) {
            \Log::error('Error updating settings: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json(['message' => 'Error saving data: ' . $e->getMessage()], 500);
        }
    }
}
