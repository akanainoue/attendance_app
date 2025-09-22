<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceRequest;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class StaffRequestController extends Controller
{
    // 詳細画面の保存（作成 or 更新）
    public function upsert(Request $request, Attendance $attendance)
    {
        abort_if($attendance->user_id !== auth()->id(), 403);

        $data = $request->validate([
            'reason'              => ['nullable','string','max:2000'],
            'clock_in_at'         => ['nullable','date'],
            'clock_out_at'        => ['nullable','date','after_or_equal:clock_in_at'],
            'breaks'              => ['nullable','array'],
            'breaks.*.start_at'   => ['nullable','date'],
            'breaks.*.end_at'     => ['nullable','date','after:breaks.*.start_at'],
        ]);

        // ペイロードを構築（指定があれば置き換え、無ければ今の勤怠値で補完）
        $payload = [
            'clock_in_at'  => $data['clock_in_at']  ?? optional($attendance->clock_in_at)->format('Y-m-d H:i:s'),
            'clock_out_at' => $data['clock_out_at'] ?? optional($attendance->clock_out_at)->format('Y-m-d H:i:s'),
            'breaks'       => isset($data['breaks'])
                ? collect($data['breaks'])->map(fn($b)=>[
                    'start_at' => isset($b['start_at']) ? Carbon::parse($b['start_at'])->format('Y-m-d H:i:s') : null,
                    'end_at'   => isset($b['end_at'])   ? Carbon::parse($b['end_at'])->format('Y-m-d H:i:s')   : null,
                  ])->values()->toArray()
                : $attendance->breaks()->orderBy('start_at')->get()->map(fn($b)=>[
                    'start_at'=>optional($b->start_at)->format('Y-m-d H:i:s'),
                    'end_at'  =>optional($b->end_at)->format('Y-m-d H:i:s'),
                  ])->values()->toArray(),
        ];

        // 既存の pending があれば更新、無ければ作成
        $req = AttendanceRequest::firstOrNew([
            'attendance_id' => $attendance->id,
            'requested_by'  => auth()->id(),
            'status'        => AttendanceRequest::STATUS_PENDING,
        ]);

        $req->reason  = $data['reason'] ?? null;   // ← nullable OK
        $req->payload = $payload;
        $req->reviewed_by = null;
        $req->reviewed_at = null;
        $req->save();

        return back()->with('status', '修正申請を保存しました（承認待ち）');
    }
}
