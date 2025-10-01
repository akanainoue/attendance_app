<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceRequest;
use App\Http\Requests\AttendanceRequest as AttendanceFormRequest;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class StaffRequestController extends Controller
{
    // 詳細画面の保存（作成 or 更新）
    public function upsert(AttendanceFormRequest $request, $id)
    {
        // abort_if($attendance->user_id !== auth()->id(), 403);

        // $data = $request->validate([
        //     'reason'              => ['nullable','string','max:2000'],
        //     'clock_in_at'         => ['nullable','date'],
        //     'clock_out_at'        => ['nullable','date','after_or_equal:clock_in_at'],
        //     'breaks'              => ['nullable','array'],
        //     'breaks.*.start_at'   => ['nullable','date'],
        //     'breaks.*.end_at'     => ['nullable','date','after:breaks.*.start_at'],
        // ]);

        // // ペイロードを構築（指定があれば置き換え、無ければ今の勤怠値で補完）
        // $payload = [
        //     'clock_in_at'  => $data['clock_in_at']  ?? optional($attendance->clock_in_at)->format('Y-m-d H:i:s'),
        //     'clock_out_at' => $data['clock_out_at'] ?? optional($attendance->clock_out_at)->format('Y-m-d H:i:s'),
        //     'breaks'       => isset($data['breaks'])
        //         ? collect($data['breaks'])->map(fn($b)=>[
        //             'start_at' => isset($b['start_at']) ? Carbon::parse($b['start_at'])->format('Y-m-d H:i:s') : null,
        //             'end_at'   => isset($b['end_at'])   ? Carbon::parse($b['end_at'])->format('Y-m-d H:i:s')   : null,
        //           ])->values()->toArray()
        //         : $attendance->breaks()->orderBy('start_at')->get()->map(fn($b)=>[
        //             'start_at'=>optional($b->start_at)->format('Y-m-d H:i:s'),
        //             'end_at'  =>optional($b->end_at)->format('Y-m-d H:i:s'),
        //           ])->values()->toArray(),
        // ];

        // // 既存の pending があれば更新、無ければ作成
        // $req = AttendanceRequest::firstOrNew([
        //     'attendance_id' => $attendance->id,
        //     'requested_by'  => auth()->id(),
        //     'status'        => AttendanceRequest::STATUS_PENDING,
        // ]);

        // $req->reason  = $data['reason'] ?? null;   // ← nullable OK
        // $req->payload = $payload;
        // $req->reviewed_by = null;
        // $req->reviewed_at = null;
        // $req->save();

        // return back()->with('status', '修正申請を保存しました（承認待ち）');

        // 本人の勤怠のみ可
        // $att = Attendance::with('breaks')->findOrFail($id);
        // if ($att->user_id !== auth()->id()) {
        //     abort(403, '自分の勤怠のみ修正できます');
        // }

        // FormRequest でバリデーション済み値
        // $data = $request->validated();

        // 勤務日の日付部分に time(H:i) を合成して保存
        // $date = Carbon::parse($att->work_date)->toDateString();
        // $att->clock_in_at  = Carbon::parse($date.' '.$data['clock_in_at']);
        // $att->clock_out_at = Carbon::parse($date.' '.$data['clock_out_at']);
        // $att->save();

        // 休憩（2つまで想定。既存をいったん消す or 個別更新はお好みで）
        // $att->breaks()->delete();
        // foreach (($data['breaks'] ?? []) as $b) {
        //     if (!empty($b['start_at']) || !empty($b['end_at'])) {
        //         $att->breaks()->create([
        //             'start_at' => !empty($b['start_at']) ? Carbon::parse($date.' '.$b['start_at']) : null,
        //             'end_at'   => !empty($b['end_at'])   ? Carbon::parse($date.' '.$b['end_at'])   : null,
        //         ]);
        //     }
        // }

        // 修正申請の upsert（例：必ず reason 必須で pending にする）
        // AttendanceRequest::updateOrCreate(
        //     ['attendance_id' => $att->id, 'requested_by' => auth()->id()],
        //     [
        //         'status'  => AttendanceRequest::STATUS_PENDING,
        //         'reason'  => $data['reason'],
        //         'payload' => [ // 任意：差分スナップショット
        //             'clock_in_at'  => $att->clock_in_at?->format('Y-m-d H:i:s'),
        //             'clock_out_at' => $att->clock_out_at?->format('Y-m-d H:i:s'),
        //             'breaks'       => $att->breaks()->orderBy('start_at')->get(['start_at','end_at']),
        //         ],
        //     ]
        // );

        // return back()->with('status', '修正内容を保存しました（承認待ち）');

        $att = Attendance::with(['breaks', 'request'])->where('user_id', auth()->id())->findOrFail($id);

        // 既に pending があるなら何もしない（連打対策）
        if (optional($att->request)->status === AttendanceRequest::STATUS_PENDING) {
            return redirect()->to("/attendance/detail/{$att->id}");
        }

        DB::transaction(function () use ($request, $att) {
            // ここでは “修正申請” を起票するだけ（元データはまだ確定変更しない）
            AttendanceRequest::create([
                'attendance_id' => $att->id,
                'requested_by'  => auth()->id(),
                'status'        => AttendanceRequest::STATUS_PENDING, // 'pending'
                'reason'        => $request->input('reason'),
                'payload'       => [
                    'clock_in_at'  => $request->input('clock_in_at'),
                    'clock_out_at' => $request->input('clock_out_at'),
                    'breaks'       => array_values($request->input('breaks', [])),
                ],
            ]);
        });

        // GET 詳細へ（そこでボタン非表示＆赤文言）
        return redirect()
            ->to("/attendance/detail/{$att->id}")
            ->with('status', '修正申請を送信しました。承認をお待ちください。');

    }

}
