<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ChangePasswordController extends Controller
{
    public function edit()
    {
        return view('settings.password', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'settings.password',
        ]));
    }

    public function update(Request $request)
    {
        $user = auth()->user();

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:4', 'confirmed'],
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'Current password is incorrect.'])->withInput();
        }

        $user->forceFill([
            'password' => Hash::make($data['password']),
        ])->save();

        return redirect()->route('settings.password')->with('success', 'Password changed successfully.');
    }
}
