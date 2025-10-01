<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class UseFortifyGuard
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next, $guard = 'web')
    {
        // Fortify が参照する guard を切り替え
        config(['fortify.guard' => $guard]);
        config(['auth.defaults.guard' => $guard]);
        return $next($request);
    }
}
