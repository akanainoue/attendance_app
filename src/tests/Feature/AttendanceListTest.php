<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Attendance;
use Carbon\Carbon;

class AttendanceListTest extends TestCase
{
    /**
     * A basic feature test example.
     *
     * @return void
     */
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user); // 一般ユーザーとしてログイン
    }

    /** ヘルパ: 勤怠を作成（時刻はダミー） */
    private function mkAttendance(User $u, string $ymd): Attendance
    {
        $d = Carbon::parse($ymd);
        return Attendance::create([
            'user_id'      => $u->id,
            'work_date'    => $d->copy()->startOfDay(),
            'clock_in_at'  => $d->copy()->setTime(9, 0),
            'clock_out_at' => $d->copy()->setTime(18, 0),
        ]);
    }

    /** @test 現在月がデフォルトで表示される */
    public function current_month_is_shown_by_default()
    {
        Carbon::setTestNow('2025-09-17 12:00:00');

        $user = \App\Models\User::factory() ->create();
        $this->actingAs($user);

        $res = $this->get('/attendance/list')->assertOk();

        $res->assertSeeText('2025/09');

        // 前月・翌月リンクのクエリ（属性値なので assertSee）
        $res->assertSee('/attendance/list?month=2025-08');
        $res->assertSee('/attendance/list?month=2025-10');
    }

    /** @test 自分の勤怠だけが一覧に出る（該当月） */
    public function it_lists_only_my_attendances_in_the_month()
    {
        // 自分: 9月に2件、10月に1件
        $a1 = $this->mkAttendance($this->user, '2025-09-05');
        $a2 = $this->mkAttendance($this->user, '2025-09-17');
        $aOtherMonth = $this->mkAttendance($this->user, '2025-10-01');

        // 他人: 9月に1件
        $other = User::factory()->create();
        $aOtherUser = $this->mkAttendance($other, '2025-09-10');

        // 9月を指定して表示
        $res = $this->get('/attendance/list?month=2025-09')->assertOk();

        // 自分の9月分のリンクは見える
        $res->assertSee('/attendance/detail/'.$a1->id);
        $res->assertSee('/attendance/detail/'.$a2->id);

        // 自分の10月分は見えない
        $res->assertDontSee('/attendance/detail/'.$aOtherMonth->id);

        // 他人の9月分は見えない
        $res->assertDontSee('/attendance/detail/'.$aOtherUser->id);
    }

    /** @test 「前月」相当: 前月を指定すると前月の月表示になる */
    public function previous_month_view_is_shown_when_prev_is_selected()
    {
        // 8月を明示
        $this->get('/attendance/list?month=2025-08')
             ->assertOk()
             ->assertSeeText('2025/08');
    }

    /** @test 「翌月」相当: 翌月を指定すると翌月の月表示になる */
    public function next_month_view_is_shown_when_next_is_selected()
    {
        // 10月を明示
        $this->get('/attendance/list?month=2025-10')
             ->assertOk()
             ->assertSeeText('2025/10');
    }

    /** @test 詳細リンクを押すと当日の勤怠詳細に遷移できる */
    public function detail_link_navigates_to_detail_page()
    {
        $att = $this->mkAttendance($this->user, '2025-09-21');

        // 一覧にリンクが出ている想定
        $this->get('/attendance/list?month=2025-09')
             ->assertOk()
             ->assertSee('/attendance/detail/'.$att->id);

        // 実際に詳細へアクセスできるか（ビューの中身はプロジェクト実装に依存）
        $this->get('/attendance/detail/'.$att->id)
             ->assertOk()
             // 例: 日付の表示を一つだけ確認（UIが異なる場合は調整）
             ->assertSee('2025年')
             ->assertSee('9月21日');
    }

    

}
