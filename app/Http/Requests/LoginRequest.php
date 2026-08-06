<?php

namespace App\Http\Requests;

use Laravel\Fortify\Fortify;
use Laravel\Fortify\Http\Requests\LoginRequest as FortifyLoginRequest;

class LoginRequest extends FortifyLoginRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            Fortify::username() => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'min:4'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'password.min' => 'Password must be at least 4 characters.',
            'password.required' => 'Password is required.',
        ];
    }
}
