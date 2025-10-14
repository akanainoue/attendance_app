<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Attendance;
use Carbon\Carbon;


class AttendanceDetailTest extends TestCase
{
    /**
     * A basic feature test example.
     *
     * @return void
     */
    use RefreshDatabase;

    /** テスト用：勤怠と休憩を用意してログイン */
    private function seedAttendance(): array
    {
        $user = User::factory()->create([
            'name' => '田中 太郎',
        ]);

        $this->actingAs($user);

        // 2025-09-17 の勤怠（09:00-18:00, 休憩 12:00-12:30 / 15:00-15:10）
        $att = Attendance::create([
            'user_id'      => $user->id,
            'work_date'    => Carbon::parse('2025-09-17'),
            'clock_in_at'  => Carbon::parse('2025-09-17 09:00:00'),
            'clock_out_at' => Carbon::parse('2025-09-17 18:00:00'),
        ]);

        $att->breaks()->create([
            'start_at' => Carbon::parse('2025-09-17 12:00:00'),
            'end_at'   => Carbon::parse('2025-09-17 12:30:00'),
        ]);
        $att->breaks()->create([
            'start_at' => Carbon::parse('2025-09-17 15:00:00'),
            'end_at'   => Carbon::parse('2025-09-17 15:10:00'),
        ]);

        return [$user, $att];
    }

    /** @test 勤怠詳細：名前・日付・出退勤・休憩が一致している（IDベース） */
    public function detail_page_shows_correct_user_date_and_times_by_id()
    {
        [$user, $att] = $this->seedAttendance();

        // /attendance/detail/{id} で詳細を表示
        $res = $this->get('/attendance/detail/'.$att->id)->assertOk();

        // ① 名前がログインユーザーの氏名
        $res->assertSee($user->name);

        // ② 日付が選択した日付
        // 画面側の表記ゆれ（YYYY/MM/DD・YYYY-MM-DD・MM/DD(曜) 等）を許容するために正規表現で確認
        $html = $res->getContent();
        // $this->assertMatchesRegularExpression(
        //     '/(2025[\/-]09[\/-]17|09\/17(?:\([月火水木金土日]\))?)/u',
        //     $html,
        //     '詳細画面の日付が期待どおりではありません'
        // );
        $res->assertSeeInOrder(['2025年', '9月17日']);

        // ③ 「出勤・退勤」に記載の時間が打刻と一致
        $res->assertSee('09:00');
        $res->assertSee('18:00');

        // ④ 「休憩」に記載の時間が打刻と一致（複数回も確認）
        // 12:00〜12:30 / 15:00〜15:10 の並びを緩く検証
        $this->assertMatchesRegularExpression('/12:00.*12:30/s', $html);
        $this->assertMatchesRegularExpression('/15:00.*15:10/s', $html);
    }
}
