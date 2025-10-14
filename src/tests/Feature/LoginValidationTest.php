<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class LoginValidationTest extends TestCase
{
    /**
     * A basic feature test example.
     *
     * @return void
     */

     use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // 日本語メッセージで検証
        config(['app.locale' => 'ja']);

        // テスト時に CSRF で 419 にならないよう無効化
        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    /* ============================
     |  一般ユーザー (/login)
     * ============================ */

    /** @test */
    public function user_login_shows_error_when_email_is_missing()
    {
        $resp = $this->from('/login')->post('/login', [
            // 'email' => '',    // 未入力
            'password' => 'secret',
        ]);

        $resp->assertRedirect('/login');
        $resp->assertSessionHasErrors([
            'email' => 'メールアドレスを入力してください',
        ]);
    }

    /** @test */
    public function user_login_shows_error_when_password_is_missing()
    {
        $resp = $this->from('/login')->post('/login', [
            'email' => 'user@example.com',
            // 'password' => '',   // 未入力
        ]);

        $resp->assertRedirect('/login');
        $resp->assertSessionHasErrors([
            'password' => 'パスワードを入力してください',
        ]);
    }

    /** @test */
    public function user_login_shows_error_when_credentials_are_invalid()
    {
        // ユーザー未作成 or パスワード不一致を想定
        $resp = $this->from('/login')->post('/login', [
            'email'    => 'user@example.com',
            'password' => 'wrong-password',
        ]);

        $resp->assertRedirect('/login');

        // 文言は運用のメッセージに合わせてください
        $this->assertTrue(
            session('errors')->has('email') &&
            str_contains(session('errors')->first('email'), 'ログイン情報が登録されていません')
        );
    }

    /* ============================
     |  管理者 (/admin/login)
     * ============================ */

    /** @test */
    public function admin_login_shows_error_when_email_is_missing()
    {
        $resp = $this->from('/admin/login')->post('/admin/login', [
            // 'email' => '',
            'password' => 'secret',
        ]);

        $resp->assertRedirect('/admin/login');
        $resp->assertSessionHasErrors([
            'email' => 'メールアドレスを入力してください',
        ]);
    }

    /** @test */
    public function admin_login_shows_error_when_password_is_missing()
    {
        $resp = $this->from('/admin/login')->post('/admin/login', [
            'email' => 'admin@example.com',
            // 'password' => '',
        ]);

        $resp->assertRedirect('/admin/login');
        $resp->assertSessionHasErrors([
            'password' => 'パスワードを入力してください',
        ]);
    }

    /** @test */
    public function admin_login_shows_error_when_credentials_are_invalid()
    {
        $resp = $this->from('/admin/login')->post('/admin/login', [
            'email'    => 'admin@example.com',
            'password' => 'wrong-password',
        ]);

        $resp->assertRedirect('/admin/login');

        // 管理者側の文言（例：自作コントローラで withErrors(['email' => '認証に失敗しました']) の場合）
        // プロジェクトの実メッセージに合わせて片方を残してください。
        $errors = session('errors');
        $this->assertTrue(
            $errors->has('email') &&
            (
                str_contains($errors->first('email'), 'ログイン情報が登録されていません') ||
                str_contains($errors->first('email'), '認証に失敗しました')
            )
        );
    }
}
