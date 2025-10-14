<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
// use App\Actions\Fortify\ResetUserPassword;
// use App\Actions\Fortify\UpdateUserPassword;
// use App\Actions\Fortify\UpdateUserProfileInformation;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);

        Fortify::loginView(fn () => view('auth.login'));
        Fortify::registerView(fn () => view('auth.register'));
        Fortify::verifyEmailView(fn () => view('auth.verify-email'));

        RateLimiter::for('login', function ($request) {
            // 1) 1分あたりの許容回数を環境で切り替え
            $limit = app()->environment('local') ? 50 : 5;

            // 2) 「誰に対して回数を数えるか」を決める識別子を作成
            $key = optional($request->user())->id            // 既に認証済なら user_id
                ?: $request->input('email').$request->ip();// まだ未認証なら「email + IP」

            // 3) 1分あたり $limit 回まで許可（超えたら 429）
            return [ Limit::perMinute($limit)->by($key) ];
        });

        Fortify::authenticateUsing(function (Request $request) {
            // LoginRequest の rules()/messages() をそのまま流用
            $form = app(\App\Http\Requests\LoginRequest::class);

            $validated = validator(
                $request->all(),
                $form->rules(),
                $form->messages()
            )->validate(); // 失敗時は ValidationException を自動throw

            // どのガードで認証するか（UseFortifyGuard ミドルウェアで設定済み）
            $guard = config('fortify.guard', 'web');

            // remember は任意
            $remember = $request->boolean('remember');

            // 認証を実行
            if (Auth::guard($guard)->attempt([
                'email'    => $validated['email'],
                'password' => $validated['password'],
            ], $remember)) {
                // 認証成功：ユーザーを返す（Fortifyがその後の処理を続ける）
                return Auth::guard($guard)->user();
            }

            // 認証失敗：好きなメッセージでValidationExceptionを投げる
            throw ValidationException::withMessages([
                Fortify::username() => 'ログイン情報が登録されていません',
            ]);
        });
    }
}
