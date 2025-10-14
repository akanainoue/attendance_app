<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
// Fortify 契約
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;
// ★ 自作クラスは Providers ではなく Http\Responses 名前空間を use
use App\Http\Responses\RegisterResponse;
use App\Http\Responses\LoginResponse;
use App\Http\Responses\LogoutResponse;
use Illuminate\Support\Carbon;         // 推奨
// use Carbon\Carbon;                  // こちらでも動きます

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(RegisterResponseContract::class, RegisterResponse::class);
        $this->app->singleton(LoginResponseContract::class, LoginResponse::class);
        $this->app->singleton(LogoutResponseContract::class, LogoutResponse::class);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // config/app.php の locale と同期しておく書き方
        Carbon::setLocale(config('app.locale', 'ja'));
    }
}
