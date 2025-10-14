<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Attendance;
use App\Models\WorkBreak;
use Illuminate\Support\Carbon;
use App\Http\Middleware\VerifyCsrfToken;

class PunchScreenTest extends TestCase
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

        // テストでは日本時間・日本語を前提にそろえる
        config(['app.timezone' => 'Asia/Tokyo', 'app.locale' => 'ja']);

        // 打刻 POST を楽にするために CSRF を無効化
        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    private function loginUser(): User
    {
        $user = User::factory()->create([
            'email_verified_at' => now(), // ← verified ミドルウェアを通す
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($user, 'web'); // web ガード
        return $user;
    }

    /** @test 現在の日時情報が UI と同じ形式で出力されること */
    public function now_is_rendered_in_the_same_format_as_ui(): void
    {
        $this->loginUser();

        // 2025/09/17 12:34 固定
        Carbon::setTestNow(Carbon::parse('2025-09-17 12:34:00', 'Asia/Tokyo'));

        $res = $this->get('/attendance')->assertOk();

        $html = $res->getContent();

        // 画面実装に合わせて “どちらか” を許容
        $y1 = now()->format('Y/m/d');
        $y2 = now()->format('Y年n月j日');
        $t1 = now()->format('H:i');

        $this->assertTrue(
            str_contains($html, $y1) || str_contains($html, $y2),
            "日付表記が UI と一致していません（{$y1} or {$y2} を含むはず）"
        );
        $this->assertTrue(
            str_contains($html, $t1),
            "時刻表記が UI と一致していません（{$t1} を含むはず）"
        );
    }

    /** @test ステータス「勤務外 / 出勤中 / 休憩中 / 退勤済」が表示されること */
    public function status_label_transitions_are_shown(): void
    {
        $this->loginUser();

        // 初期：勤務外
        Carbon::setTestNow('2025-09-17 08:50:00');
        $this->get('/attendance')->assertOk()->assertSee('勤務外');

        // 出勤 → 出勤中
        Carbon::setTestNow('2025-09-17 09:00:00');
        $this->post('/attendance/clock-in')->assertRedirect();
        $this->get('/attendance')->assertSee('出勤中');

        // 休憩入 → 休憩中
        Carbon::setTestNow('2025-09-17 12:00:00');
        $this->post('/attendance/break-start')->assertRedirect();
        $this->get('/attendance')->assertSee('休憩中');

        // 休憩戻 → 出勤中
        Carbon::setTestNow('2025-09-17 12:30:00');
        $this->post('/attendance/break-end')->assertRedirect();
        $this->get('/attendance')->assertSee('出勤中');

        // 退勤 → 退勤済
        Carbon::setTestNow('2025-09-17 18:00:00');
        $this->post('/attendance/clock-out')->assertRedirect();
        $this->get('/attendance')->assertSee('退勤済');
    }

    /** @test 出勤は 1 日 1 回だけ／2 回目は無効 */
    public function clock_in_only_once_per_day(): void
    {
        $this->loginUser();

        \Carbon\Carbon::setTestNow('2025-09-17 09:00:00');
        $this->post('/attendance/clock-in')->assertRedirect(); // 1回目は成功→302

        \Carbon\Carbon::setTestNow('2025-09-17 09:10:00');
        $this->post('/attendance/clock-in')->assertStatus(422); // 2回目は422
    }

    /** @test 退勤は 1 日 1 回だけ／2 回目は無効 */
    public function clock_out_only_once_per_day(): void
    {
        $this->loginUser();

        \Carbon\Carbon::setTestNow('2025-09-17 09:00:00');
        $this->post('/attendance/clock-in');

        \Carbon\Carbon::setTestNow('2025-09-17 18:00:00');
        $this->post('/attendance/clock-out')->assertRedirect(); // 1回目→302

        \Carbon\Carbon::setTestNow('2025-09-17 18:10:00');
        $this->post('/attendance/clock-out')->assertStatus(422); // 2回目→422
    }

    /** @test 休憩は複数回できる（2 回分のレコードが残る） */
    public function multiple_breaks_can_be_taken_in_a_day(): void
    {
        $this->loginUser();

        \Carbon\Carbon::setTestNow('2025-09-17 09:00:00');
        $this->post('/attendance/clock-in')->assertRedirect();

        // 休憩1
        \Carbon\Carbon::setTestNow('2025-09-17 12:00:00');
        $this->post('/attendance/break-start')->assertRedirect();

        \Carbon\Carbon::setTestNow('2025-09-17 12:30:00');
        $this->post('/attendance/break-end')->assertRedirect();

        // 休憩2
        \Carbon\Carbon::setTestNow('2025-09-17 15:00:00');
        $this->post('/attendance/break-start')->assertRedirect();

        \Carbon\Carbon::setTestNow('2025-09-17 15:10:00');
        $this->post('/attendance/break-end')->assertRedirect();

        // DB 確認（テーブル名はあなたの実装に合わせて）
        $this->assertDatabaseCount('breaks', 2);
    }
}
