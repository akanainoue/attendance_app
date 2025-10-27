<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
// use Illuminate\Http\Request;
use App\Http\Requests\LoginRequest;
use Illuminate\Support\Facades\Auth;

class AuthenticatedSessionController extends Controller
{
    public function create()
    {
        return view( 'admin.auth.login');
    }

    
}


