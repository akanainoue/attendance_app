<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Attendance;
use App\Models\AttendanceRequest;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PunchController extends Controller
{
    public function show()
    {
        $attendance = Attendance::firstOrCreate([
            'user_id'   => auth()->id(),
            'work_date' => today(),
        ]);

        $status = $attendance->status(); // ← これを渡す
        $date   = now()->locale('ja')->isoFormat('YYYY年M月D日(dd)');
        $time   = now()->format('H:i');

        return response()
            ->view('user.attendances.punch', compact('attendance','status','date','time'))
            ->header('Cache-Control','no-store');
        // $today = now()->toDateString();
        // $attendance = Attendance::firstOrCreate(
        //     ['user_id'=>auth()->id(),'work_date'=>$today]
        // );
        // return view('user.attendances.punch', compact('attendance'));
    }

    public function clockIn()
    {
        $att = Attendance::where('user_id',auth()->id())->whereDate('work_date',today())->firstOrFail();
        abort_if($att->clock_in_at, 422, 'すでに出勤済みです');
        $att->update(['clock_in_at'=>now()]);
        return redirect('/attendance'); // ← back() ではなく
    }
    

    public function breakStart()
    {
        $att = Attendance::where('user_id',auth()->id())->whereDate('work_date',today())->firstOrFail();
        abort_if(!$att->clock_in_at || $att->clock_out_at, 422);
        abort_if($att->breaks()->whereNull('end_at')->exists(), 422);
        $att->breaks()->create(['start_at'=>now()]);
        return redirect('/attendance'); // ← back() ではなく
    }

    public function breakEnd()
    {
        $att = Attendance::where('user_id',auth()->id())->whereDate('work_date',today())->firstOrFail();
        $break = $att->breaks()->whereNull('end_at')->latest('start_at')->first();
        abort_if(!$break, 422);
        $break->update(['end_at'=>now()]);
        return redirect('/attendance'); // ← back() ではなく
    }

    public function clockOut()
    {
        $att = Attendance::where('user_id',auth()->id())->whereDate('work_date',today())->firstOrFail();
        abort_if(!$att->clock_in_at || $att->clock_out_at, 422, '退勤済みです。');
        // 休憩が開いていれば自動で閉じる
        if ($open = $att->breaks()->whereNull('end_at')->latest()->first()) {
            $open->update(['end_at'=>now()]);
        }
        // 退勤
        $att->update(['clock_out_at'=>now()]);

        // ★ 申請（pending, reason=NULL）を自動作成（既にpendingがあれば作らない）
        AttendanceRequest::firstOrCreate([
            'attendance_id' => $att->id,
            'requested_by'  => auth()->id(),
            'status'        => AttendanceRequest::STATUS_PENDING,
        ], [
            'reason'  => null,
            'payload' => $this->toPayload($att), // 現状値のスナップショット
        ]);

        return redirect('/attendance')->with('status', '退勤しました（申請下書きを作成）');
    }

    private function toPayload(Attendance $att): array
    {
        return [
            'clock_in_at'  => optional($att->clock_in_at)->format('Y-m-d H:i:s'),
            'clock_out_at' => optional($att->clock_out_at)->format('Y-m-d H:i:s'),
            'breaks'       => $att->breaks()->orderBy('start_at')->get()
                                ->map(fn($b)=>[
                                    'start_at'=>optional($b->start_at)->format('Y-m-d H:i:s'),
                                    'end_at'  =>optional($b->end_at)->format('Y-m-d H:i:s'),
                                ])->values()->toArray(),
        ];
    }
}