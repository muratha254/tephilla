<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class SwitchUserController extends Controller
{
    /**
     * Get users list for AJAX/modal.
     */
    public function getUsers()
    {
        $currentUser = auth()->user();
        $users = User::query()
            ->where('id', '!=', $currentUser->id)
            ->when(
                ! $currentUser->hasRole(User::ROLE_ADMIN) && ! (($currentUser->level ?? 0) == 1),
                fn ($q) => $q->isNotAdmin()
            )
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role']);

        return response()->json($users);
    }

    /**
     * Show the list of users to switch to.
     */
    public function index()
    {
        $currentUser = auth()->user();
        $users = User::query()
            ->where('id', '!=', $currentUser->id)
            ->when(
                ! $currentUser->hasRole(User::ROLE_ADMIN) && ! (($currentUser->level ?? 0) == 1),
                fn ($q) => $q->isNotAdmin()
            )
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role']);

        return view('switch_user.index', compact('users'));
    }

    /**
     * Switch the current session to the given user.
     */
    public function switch(Request $request, User $user)
    {
        $currentUser = auth()->user();

        // Prevent switching to self
        if ($user->id === $currentUser->id) {
            return redirect()->route('switch-user.index')
                ->withErrors(['error' => 'You cannot switch to yourself.']);
        }

        // Non-admins cannot switch to admin users
        if (
            ! $currentUser->hasRole(User::ROLE_ADMIN) && (($currentUser->level ?? 0) != 1)
            && ($user->hasRole(User::ROLE_ADMIN) || ($user->level ?? 0) == 1)
        ) {
            return redirect()->route('switch-user.index')
                ->withErrors(['error' => 'You cannot switch to an administrator.']);
        }

        // Verify the target user's password
        $password = $request->input('password');
        if (empty($password) || ! Hash::check($password, $user->password)) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['error' => 'Invalid password.'], 422);
            }
            return back()->withErrors(['error' => 'Invalid password. Please enter the correct password for ' . $user->name . '.']);
        }

        Auth::login($user, $request->boolean('remember', false));

        // Cashiers go to POS (transaksi); others go to dashboard
        $target = $user->hasRole(User::ROLE_CASHIER)
            ? route('transaksi.index')
            : route('dashboard');

        return redirect($target)
            ->with('success', 'Switched to ' . $user->name . '.');
    }
}
