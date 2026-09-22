<?php

namespace App\Http\Responses;

use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request)
    {
        if ($request->wantsJson()) {
            return response()->json(['two_factor' => false]);
        }

        if ($request->user() && $request->user()->isSystemOwner()) {
            return redirect()->intended(route('owner.dashboard'));
        }

        $user = $request->user();
        $subscription = ($user && $user->company) ? $user->company->subscription : null;
        if ($subscription && ! $subscription->allowsAccess()) {
            return redirect()->route('subscription.blocked', [
                'reason' => $subscription->effectiveStatus(),
            ]);
        }

        return redirect()->intended(route('dashboard'));
    }
}
