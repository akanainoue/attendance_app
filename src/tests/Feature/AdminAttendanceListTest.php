<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Admin;
use App\Models\Attendance;
use Illuminate\Support\Carbon;

class AdminAttendanceListTest extends TestCase
{
    /**
     * A basic feature test example.
     *
     * @return void
     */
    use RefreshDatabase;

    /* ---------- helpers ---------- */

    private function loginAdmin(): \App\Models\Admin
    {
        // email_verified_at は付けない（管理者はメ ール認証不要）
        $admin = \App\Models\Admin::create([
            'name'     => '管理者',
            'email'    => 'admin@example.com',
            'password' => bcrypt('password'),
        ]);

        // adminガードでログイン
        $this->actingAs($admin, 'admin');

        return $admin;
    }


    private function mkUser(string $name, string $email): User
    {
        return User::create([
            'name'              => $name,
            'email'             => $email,
            'email_verified_at' => now(),
            'password'          => bcrypt('password'),
        ]);
    }

    /**
     * 勤怠1件を素直に create（factory不使用）
     * $in/$out は 'H:i'、$date は 'Y-m-d'
     */
    private function mkAttendance(User $u, string $date, ?string $in, ?string $out): Attendance
    {
        return Attendance::create([
            'user_id'      => $u->id,
            'work_date'    => Carbon::parse($date),
            'clock_in_at'  => $in  ? Carbon::parse("$date $in:00")  : null,
            'clock_out_at' => $out ? Carbon::parse("$date $out:00") : null,
        ]);
    }

    /* ---------- tests ---------- */

    /** @test その日になされた全ユーザーの勤怠が正しく確認できる */
    public function it_shows_all_users_attendance_of_the_day(): void
    {
        $this->loginAdmin();

        $date = '2025-10-01';

        $alice = $this->mkUser('田中 太郎',    'alice@example.com');
        $bob   = $this->mkUser('佐藤 花子',    'bob@example.com');
        $char  = $this->mkUser('鈴木 三郎',    'charlie@example.com');

        // 当日の勤怠（2人分）
        $this->mkAttendance($alice, $date, '09:00', '18:00');
        $this->mkAttendance($bob,   $date, '10:00', '19:00');

        // 別日の勤怠（当日には出ない）
        $this->mkAttendance($char,  '2025-10-02', '09:00', '18:00');

        $res = $this->get('/admin/attendance/list?date='.$date)->assertOk();

        // 当日の2人分が見える
        $res->assertSee('田中 太郎')->assertSee('09:00')->assertSee('18:00');
        $res->assertSee('佐藤 花子')->assertSee('10:00')->assertSee('19:00');

        // 別日のレコードは表示されない
        $res->assertDontSee('鈴木 三郎');
    }

    /** @test 遷移した際に現在の日付が表示される（date未指定は当日） */
    public function it_shows_today_by_default(): void
    {
        $this->loginAdmin();

        Carbon::setTestNow('2025-09-17 11:22:33');

        $u = $this->mkUser('山田 次郎', 'yamada@example.com');
        $this->mkAttendance($u, '2025-09-17', '09:00', '18:00');

        $res  = $this->get('/admin/attendance/list')->assertOk();
        $html = $res->getContent();

        // 画面の年月日（例：2025/09/17 または 2025-09-17 など）を許容
        $this->assertMatchesRegularExpression('/(2025[\/-]09[\/-]17|09\/17(?:\([月火水木金土日]\))?)/u', $html);
    }

    /** @test 「前日」「翌日」リンクで前後の日付に移動できる */
    public function it_moves_prev_and_next_day(): void
    {
        $this->loginAdmin();

        $base = '2025-10-15';

        // 前後の日にも誰か1件ずつ置いて、リンク遷移後のページでも 200 が返ることを確認
        $u = $this->mkUser('小林 四郎', 'koba@example.com');
        $this->mkAttendance($u, '2025-10-14', '09:00', '18:00');
        $this->mkAttendance($u, '2025-10-15', '09:00', '18:00');
        $this->mkAttendance($u, '2025-10-16', '09:00', '18:00');

        $res  = $this->get('/admin/attendance/list?date='.$base)->assertOk();
        $html = $res->getContent();

        // 前日／翌日へのリンクが正しい日付を含むことを確認
        $res->assertSee('/admin/attendance/list?date=2025-10-14');
        $res->assertSee('/admin/attendance/list?date=2025-10-16');

        // 実際に辿ってOKが返る
        $this->get('/admin/attendance/list?date=2025-10-14')->assertOk();
        $this->get('/admin/attendance/list?date=2025-10-16')->assertOk();
    }

}
