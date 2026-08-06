<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;

class LogoutResponse implements LogoutResponseContract
{
    public function toResponse($request)
    {
        return $request->wantsJson()
            ? response()->json(['message' => 'Successfully Logged out !'])
            : redirect()->route('login')->with('status', 'Successfully Logged out !');
    }
}
