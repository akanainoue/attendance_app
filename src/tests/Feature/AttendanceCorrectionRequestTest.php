<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Illuminate\Support\Carbon;
use App\Models\User;
use App\Models\Attendance;
use App\Models\AttendanceRequest;

class AttendanceCorrectionRequestTest extends TestCase
{
    /**
     * A basic feature test example.
     *
     * @return void
     */

    use RefreshDatabase;

    private function loginUser(): User
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => bcrypt('password'),
        ]);
        $this->actingAs($user);
        return $user;
    }

    private function makeAttendance(User $user, string $ymd = '2025-09-17'): Attendance
    {
        return Attendance::create([
            'user_id'   => $user->id,
            'work_date' => Carbon::parse($ymd),
            'clock_in_at'  => null,
            'clock_out_at' => null,
        ]);
    }

    /** @test 出勤が退勤より後 → work_time エラー */
    public function clock_in_later_than_clock_out_is_rejected_with_work_time_message()
    {
        $u   = $this->loginUser();
        $att = $this->makeAttendance($u, '2025-09-17');

        $detailUrl = "/attendance/detail/{$att->id}";

        $res = $this->from($detailUrl)->post("/attendance/detail/{$att->id}/notes", [
            'clock_in_at'  => '18:00',
            'clock_out_at' => '09:00',
            'breaks'       => [],
            'reason'       => '修正お願いします。',
        ]);

        $res->assertRedirect($detailUrl);
        $res->assertSessionHasErrors([
            // AttendanceFormRequest::withValidator で追加している集約メッセージ
            'work_time' => '出勤時間もしくは退勤時間が不適切な値です',
        ]);
    }

    /** @test 休憩開始が退勤より後 → break_time エラー */
    public function break_start_later_than_clock_out_is_rejected_with_break_time_message()
    {
        $u   = $this->loginUser();
        $att = $this->makeAttendance($u, '2025-09-17');

        $detailUrl = "/attendance/detail/{$att->id}";

        $res = $this->from($detailUrl)->post("/attendance/detail/{$att->id}/notes", [
            'clock_in_at'  => '09:00',
            'clock_out_at' => '18:00',
            'breaks'       => [
                ['start_at' => '19:00', 'end_at' => '19:10'], // 退勤後に休憩開始
            ],
            'reason'       => '修正お願いします。',
        ]);

        $res->assertRedirect($detailUrl);
        $res->assertSessionHasErrors([
            'break_time' => '休憩時間が不適切な値です',
        ]);
    }

    /** @test 休憩終了が開始より前 → break_time エラー */
    public function break_end_earlier_than_start_is_rejected_with_break_time_message()
    {
        $u   = $this->loginUser();
        $att = $this->makeAttendance($u, '2025-09-17');

        $detailUrl = "/attendance/detail/{$att->id}";

        $res = $this->from($detailUrl)->post("/attendance/detail/{$att->id}/notes", [
            'clock_in_at'  => '09:00',
            'clock_out_at' => '18:00',
            'breaks'       => [
                ['start_at' => '12:30', 'end_at' => '12:00'], // 開始より前に終了
            ],
            'reason'       => '修正お願いします。',
        ]);

        $res->assertRedirect($detailUrl);
        $res->assertSessionHasErrors([
            'break_time' => '休憩時間が不適切な値です',
        ]);
    }

    /** @test 出勤の形式不正 → clock_in_at の個別メッセージ */
    public function invalid_clock_in_format_shows_field_message()
    {
        $u   = $this->loginUser();
        $att = $this->makeAttendance($u, '2025-09-17');

        $detailUrl = "/attendance/detail/{$att->id}";

        $res = $this->from($detailUrl)->post("/attendance/detail/{$att->id}/notes", [
            'clock_in_at'  => '9時',     // フォーマット不正
            'clock_out_at' => '18:00',
            'breaks'       => [],
            'reason'       => '修正お願いします。',
        ]);

        $res->assertRedirect($detailUrl);
        $res->assertSessionHasErrors([
            'clock_in_at' => '出勤時刻の形式が不正です（例: 09:00）',
        ]);
    }

    /** @test 休憩の形式不正 → breaks.* の個別メッセージ（例） */
    public function invalid_break_format_shows_field_message()
    {
        $u   = $this->loginUser();
        $att = $this->makeAttendance($u, '2025-09-17');

        $detailUrl = "/attendance/detail/{$att->id}";

        $res = $this->from($detailUrl)->post("/attendance/detail/{$att->id}/notes", [
            'clock_in_at'  => '09:00',
            'clock_out_at' => '18:00',
            'breaks'       => [
                ['start_at' => 'お昼', 'end_at' => '12:30'], // start の形式不正
            ],
            'reason'       => '修正お願いします。',
        ]);

        $res->assertRedirect($detailUrl);
        // Laravel は配列要素のキーとして breaks.0.start_at を使う
        $res->assertSessionHasErrors([
            'breaks.0.start_at' => '休憩時間の形式が不正です（例: 12:30）',
        ]);
    }
    
    /** @test 備考が未入力なら reason.required メッセージ */
    public function reason_is_required_and_shows_japanese_message()
    {
        $u   = $this->loginUser();
        $att = $this->makeAttendance($u, '2025-09-17');
    
        $detailUrl = "/attendance/detail/{$att->id}";
    
        // reason 未入力（空文字でも可）
        $res = $this->from($detailUrl)->post("/attendance/detail/{$att->id}/notes", [
            'clock_in_at'  => '09:00',
            'clock_out_at' => '18:00',
            'breaks'       => [],
            'reason'       => '', // ← 未入力扱い
        ]);
    
        $res->assertRedirect($detailUrl);
        $res->assertSessionHasErrors([
            'reason' => '備考を記入してください',
        ]);
    }

}
