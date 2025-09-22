<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request)
    {
        return $request->user()->hasVerifiedEmail()
            ? redirect()->intended('/attendance')
            : redirect()->route('verification.notice');   // /email/verify
    }
}



