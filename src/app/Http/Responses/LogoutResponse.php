<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;

class LogoutResponse implements LogoutResponseContract
{
    public function toResponse($request)
    {
        // 画面遷移：管理者は /admin/login、一般ユーザーは /login へ
        $to = $request->is('admin/*') ? '/admin/login' : '/login';
        return redirect($to);
    }
}

