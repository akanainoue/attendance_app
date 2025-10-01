<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

use Illuminate\Support\Facades\Auth;                // ← 追加
use Illuminate\Contracts\Auth\MustVerifyEmail;      // ← 追加

class LoginResponse implements LoginResponseContract
{
    // public function toResponse($request)
    // {
    //     return $request->user()->hasVerifiedEmail()
    //         ? redirect()->intended('/attendance')
    //         : redirect()->route('verification.notice');   // /email/verify
    // }

    public function toResponse($request)
    {
        // 管理者ガードでログインしている？
        if (Auth::guard('admin')->check()) {
            return redirect()->intended('/admin/attendance/list'); // 例：管理者勤怠一覧
        }

        // 一般ユーザー（webガード）
        if (Auth::guard('web')->check()) {
            $user = Auth::guard('web')->user();

            // MustVerifyEmail 実装時のみチェック
            if ($user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail()) {
                return redirect()->route('verification.notice'); // /email/verify
            }
            return redirect()->intended('/attendance');
        }

        // どちらでもない（想定外）はログインへ
        // return redirect('/login');
    }
}



