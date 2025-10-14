<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Illuminate\Support\Carbon;
use App\Models\User;
use App\Models\Attendance;
use App\Models\AttendanceRequest;

class CorrectionRequestListTest extends TestCase
{
    /**
     * A basic feature test example.
     *
     * @return void
     */
    use RefreshDatabase;

    /** Attendance を factory/forceFill なしで作成 */
    private function makeAttendance(User $user, string $ymd, ?string $in = null, ?string $out = null): Attendance
    {
        return Attendance::create([
            'user_id'      => $user->id,
            'work_date'    => Carbon::parse($ymd),
            'clock_in_at'  => $in  ? Carbon::parse("$ymd $in")  : null,
            'clock_out_at' => $out ? Carbon::parse("$ymd $out") : null,
        ]);
    }

    /** 申請を作成 */
    private function makeRequest(Attendance $att, int $requestedBy, string $status): AttendanceRequest
    {
        return AttendanceRequest::create([
            'attendance_id' => $att->id,
            'requested_by'  => $requestedBy,
            'status'        => $status,
            'reason'        => '理由',
            'payload'       => [
                'clock_in_at'  => optional($att->clock_in_at)->format('H:i'),
                'clock_out_at' => optional($att->clock_out_at)->format('H:i'),
                'breaks'       => [],
            ],
        ]);
    }

    /** @test 承認待ちタブは自分の申請だけ表示 */
    public function pending_tab_lists_my_requests_only()
    {
        $me  = User::factory()->create(['email_verified_at' => now()]);
        $you = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($me);

        $a1 = $this->makeAttendance($me, '2025-10-01', '09:00', '18:00');
        $a2 = $this->makeAttendance($me, '2025-10-02');
        $a3 = $this->makeAttendance($you, '2025-10-03', '09:00', '18:00'); // 他人

        $this->makeRequest($a1, $me->id,  AttendanceRequest::STATUS_PENDING);
        $this->makeRequest($a2, $me->id,  AttendanceRequest::STATUS_PENDING);
        $this->makeRequest($a3, $you->id, AttendanceRequest::STATUS_PENDING);

        $res = $this->get('/stamp_correction_request/list?tab=pending')->assertOk();

        $res->assertSee('/attendance/detail/'.$a1->id);
        $res->assertSee('/attendance/detail/'.$a2->id);
        $res->assertDontSee('/attendance/detail/'.$a3->id);
    }

    /** @test 承認済みタブは自分の承認済みだけ表示 */
    public function approved_tab_lists_my_approved_requests_only()
    {
        $me = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($me);

        $aP1 = $this->makeAttendance($me, '2025-10-01', '09:00', '18:00');
        $aP2 = $this->makeAttendance($me, '2025-10-02', '10:00', '19:00');
        $aNg = $this->makeAttendance($me, '2025-10-03');

        $this->makeRequest($aP1, $me->id, AttendanceRequest::STATUS_APPROVED);
        $this->makeRequest($aP2, $me->id, AttendanceRequest::STATUS_APPROVED);
        $this->makeRequest($aNg, $me->id, AttendanceRequest::STATUS_PENDING); // 表示されない

        $res = $this->get('/stamp_correction_request/list?tab=approved')->assertOk();

        $res->assertSee('/attendance/detail/'.$aP1->id);
        $res->assertSee('/attendance/detail/'.$aP2->id);
        $res->assertDontSee('/attendance/detail/'.$aNg->id);
    }
}
