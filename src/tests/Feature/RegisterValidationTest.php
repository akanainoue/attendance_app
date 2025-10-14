<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class RegisterValidationTest extends TestCase
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
        // 日本語メッセージで検証（RegisterRequest::messages でも上書きされますが念のため）
        app()->setLocale('ja');
    }

    /** 登録用の POST ヘルパ */
    private function postRegister(array $overrides = [])
    {
        $valid = [
            'name'                  => '山田 太郎',
            'email'                 => 'taro@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ];

        $payload = array_merge($valid, $overrides);

        // from() を付けるとバリデーション失敗時のリダイレクト先を /register に固定できる
        return $this->from(route('register'))->post(route('register'), $payload);
    }

    /** @test 名前が未入力だとメッセージが出る */
    public function name_is_required()
    {
        $res = $this->postRegister(['name' => '']);

        $res->assertRedirect(route('register'));
        // メッセージまで検証（RegisterRequest::messages の値）
        $res->assertSessionHasErrors(['name' => 'お名前を入力してください']);

        $this->assertDatabaseMissing('users', ['email' => 'taro@example.com']);
    }

    /** @test メールアドレスが未入力だとメッセージが出る */
    public function email_is_required()
    {
        $res = $this->postRegister(['email' => '']);

        $res->assertRedirect(route('register'));
        $res->assertSessionHasErrors(['email' => 'メールアドレスを入力してください']);

        $this->assertDatabaseMissing('users', ['email' => '']);
    }

    /** @test パスワードが未入力だとメッセージが出る */
    public function password_is_required()
    {
        $res = $this->postRegister([
            'password'              => '',
            'password_confirmation' => '',
        ]);

        $res->assertRedirect(route('register'));
        $res->assertSessionHasErrors(['password' => 'パスワードを入力してください']);

        $this->assertDatabaseMissing('users', ['email' => 'taro@example.com']);
    }

    /** @test パスワードが8文字未満だとメッセージが出る */
    public function password_must_be_at_least_8_characters()
    {
        $res = $this->postRegister([
            'password'              => 'short7',
            'password_confirmation' => 'short7',
        ]);

        $res->assertRedirect(route('register'));
        $res->assertSessionHasErrors(['password' => 'パスワードは８文字以上で入力してください']);

        $this->assertDatabaseMissing('users', ['email' => 'taro@example.com']);
    }

    /** @test 確認用パスワードが一致しないとメッセージが出る */
    public function password_confirmation_must_match()
    {
        $res = $this->postRegister([
            'password'              => 'password123',
            'password_confirmation' => 'different123',
        ]);

        $res->assertRedirect(route('register'));
        $res->assertSessionHasErrors(['password' => 'パスワードと一致しません']);

        $this->assertDatabaseMissing('users', ['email' => 'taro@example.com']);
    }

    /** @test 正しい入力ならユーザーが保存される */
    public function valid_payload_creates_a_user()
    {
        $res = $this->postRegister(); // すべて有効

        // Fortify の既定ではメール認証を有効にしている場合 /email/verify に飛ぶ
        $res->assertStatus(302);

        $this->assertDatabaseHas('users', [
            'email' => 'taro@example.com',
            'name'  => '山田 太郎',
        ]);

        // 登録後にログイン状態になる設定なら以下でもOK
        // $this->assertAuthenticated();
    }
}
