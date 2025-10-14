<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Admin;
use App\Models\Attendance;

class AdminStaffInfoTest extends TestCase
{
    /**
     * A basic feature test example.
     *
     * @return void
     */
    use RefreshDatabase;

    /* ========= helpers ========= */

    private function loginAdmin(): Admin
    {
        $admin = Admin::create([
            'name'     => '管理者',
            'email'    => 'admin@example.com',
            'password' => bcrypt('password'), // メール認証なし
        ]);

        $this->actingAs($admin, 'admin');

        return $admin;
    }

    private function mkUser(string $name, string $email): User
    {
        return User::factory()->create([
            'name'  => $name,
            'email' => $email,
        ]);
    }

    /**
     * Attendance は factory を使わず create で作成
     * $breaks は [['12:00','12:30'], ...] の形式（必要なら）
     */
    private function mkAtt(User $u, string $ymd, ?string $in, ?string $out, array $breaks = []): Attendance
    {
        $a = Attendance::create([
            'user_id'     => $u->id,
            'work_date'   => $ymd,
            'clock_in_at' => $in  ? "{$ymd} {$in}:00"  : null,
            'clock_out_at'=> $out ? "{$ymd} {$out}:00" : null,
        ]);

        foreach ($breaks as [$s, $e]) {
            $a->breaks()->create([
                'start_at' => "{$ymd} {$s}:00",
                'end_at'   => "{$ymd} {$e}:00",
            ]);
        }

        return $a;
    }

    /* ========= tests ========= */

    /** @test 管理者ユーザーが全一般ユーザーの氏名・メールを確認できる */
    public function admin_can_view_all_users_name_and_email(): void
    {
        $this->loginAdmin();
        $u1 = $this->mkUser('田中 太郎', 'taro@example.com');
        $u2 = $this->mkUser('山田 花子', 'hanako@example.com');

        $res = $this->get('/admin/staff/list')->assertOk();

        $res->assertSee('田中 太郎')
            ->assertSee('taro@example.com')
            ->assertSee('山田 花子')
            ->assertSee('hanako@example.com');
    }

    /** @test ユーザーの勤怠情報が正しく表示される（月次） */
    public function admin_sees_selected_users_month_attendance_correctly(): void
    {
        $this->loginAdmin();
        $u = $this->mkUser('田中 太郎', 'taro@example.com');

        // 2025-10 の実データ（1日のみ中身あり）
        $a101 = $this->mkAtt($u, '2025-10-01', '09:00', '18:00', [['12:00','12:30']]);
        // 別月データ（表示対象外）
        $this->mkAtt($u, '2025-09-30', '09:00', '18:00');

        $res = $this->get("/admin/attendance/staff/{$u->id}")
            ->assertOk();

        // 画面ヘッダ等に当月表記
        $res->assertSee('2025/10');

        // 10/01 の行と、詳細リンク（IDベース）が出ている
        $res->assertSee('10/01')
            ->assertSee('09:00')
            ->assertSee('18:00')
            ->assertSee('/admin/attendance/'.$a101->id);

        // 9月分は混じらない
        $res->assertDontSee('09/30');
    }

    /** @test 「前月」リンクを押すと前月が表示される（リンクが正しい） */
    public function prev_month_link_points_to_previous_month(): void
    {
        $this->loginAdmin();
        $u = $this->mkUser('田中 太郎', 'taro@example.com');

        $this->mkAtt($u, '2025-10-01', '09:00', '18:00');

        $res = $this->get("/admin/attendance/staff/{$u->id}?month=2025-10")->assertOk();

        // 前月リンクが ym=2025-09 を含む
        $res->assertSee("/admin/attendance/staff/{$u->id}?month=2025-09");
    }

    /** @test 「翌月」リンクを押すと翌月が表示される（リンクが正しい） */
    public function next_month_link_points_to_next_month(): void
    {
        $this->loginAdmin();
        $u = $this->mkUser('田中 太郎', 'taro@example.com');

        $this->mkAtt($u, '2025-10-01', '09:00', '18:00');

        $res = $this->get("/admin/attendance/staff/{$u->id}?month=2025-10")->assertOk();

        // 翌月リンクが ym=2025-11 を含む
        $res->assertSee("/admin/attendance/staff/{$u->id}?month=2025-11");
    }

    /** @test 「詳細」を押すとその日の勤怠詳細へ（リンク存在の検証） */
    public function detail_link_points_to_attendance_detail_page(): void
    {
        $this->loginAdmin();
        $u = $this->mkUser('田中 太郎', 'taro@example.com');
        $a = $this->mkAtt($u, '2025-10-05', '10:00', '19:00');

        $res = $this->get("/admin/attendance/staff/{$u->id}?month=2025-10")->assertOk();

        $res->assertSee('/admin/attendance/'.$a->id);
        // 実際に遷移まで確認したい場合は、管理者でも閲覧可能な詳細ルートを用意した上で:
        $this->get('/admin/attendance/'.$a->id)->assertOk();
    }
}
