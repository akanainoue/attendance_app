<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\URL;

class EmailVerificationTest extends TestCase
{
    /**
     * A basic feature test example.
     *
     * @return void
     */
    use RefreshDatabase;

    /** @test 会員登録後、認証メールが送信される */
    public function verification_mail_is_sent_after_register()
    {
        Notification::fake();

        // 会員登録
        $payload = [
            'name'                  => '田中 太郎',
            'email'                 => 'taro@example.com',
            'password'              => 'password',
            'password_confirmation' => 'password',
        ];
        $this->post('/register', $payload)->assertRedirect(route('verification.notice'));

        // 登録ユーザーが作られ、VerifyEmail 通知が送られている
        $user = User::where('email', 'taro@example.com')->firstOrFail();
        Notification::assertSentTo($user, VerifyEmail::class);
        $this->assertNull($user->email_verified_at);
    }

    /** @test 認証誘導画面から認証サイト（署名付きURL）に遷移できる */
    public function verification_notice_to_signed_verify_url_works()
    {
        Notification::fake();

        $user = User::create([
            'name'     => '田中 太郎',
            'email'    => 'taro@example.com',
            'password' => bcrypt('password'),
        ]);

        // 誘導画面が見える（未認証ユーザーでログイン）
        $this->actingAs($user)->get(route('verification.notice'))->assertOk();

        // メールに入る署名付きURLを生成 & 叩けることを確認（ここでは 403/404 にならないこと）
        $verifyUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        // $this->get($verifyUrl)->assertRedirect('/attendance'); // ← 次のテスト要件も満たす
        $res = $this->get($verifyUrl)->assertRedirect();
        $this->assertStringStartsWith(
            'http://localhost/attendance',
            $res->headers->get('Location')
        );
    }

    /** @test 認証を完了すると勤怠登録画面（/attendance）へ遷移できる */
    public function after_verify_user_is_redirected_to_attendance_page()
    {
        $user = User::create([
            'name'     => '田中 太郎',
            'email'    => 'taro@example.com',
            'password' => bcrypt('password'),
        ]);

        // 未認証時は勤怠画面にアクセスできず、誘導へ飛ばされる
        $this->actingAs($user)->get('/attendance')->assertRedirect(route('verification.notice'));

        // 署名付きURLで認証完了
        $verifyUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );
        // $this->get($verifyUrl)->assertRedirect('/attendance');
        $res = $this->get($verifyUrl)->assertRedirect();
        $this->assertStringStartsWith(
            'http://localhost/attendance',
            $res->headers->get('Location')
        );
        // /attendance?verified=1 にリダイレクトで ?verified=1 が付いていてもパスの先頭が /attendance であることを検証できる

        // 認証済みになっている & 勤怠登録画面が表示できる
        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
        $this->get('/attendance')->assertOk();
    }
}
