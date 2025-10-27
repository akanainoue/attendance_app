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
        $att = Attendance::with(['breaks', 'request'])->where('user_id', auth()->id())->findOrFail($id);

        // 既に pending があるなら何もしない（連打対策）
        if (optional($att->request)->status === AttendanceRequest::STATUS_PENDING) {
            return redirect()->to("/attendance/detail/{$att->id}");
        }

        // DB::transaction(function () use ($request, $att) {
            // ここでは “修正申請” を起票するだけ（元データはまだ確定変更しない）
            AttendanceRequest::create([
                'attendance_id' => $att->id,
                'requested_by'  => auth()->id(),
                'status'        => AttendanceRequest::STATUS_PENDING, // 'pending'
                'reason'        => $request->input('reason'),
                'payload'       => [
                    'work_date' => optional($att->work_date)->toDateString(),
                    'clock_in_at'  => $request->input('clock_in_at'),
                    'clock_out_at' => $request->input('clock_out_at'),
                    'breaks'       => array_values($request->input('breaks', [])),
                ],
            ]);
        // });

        // GET 詳細へ（そこでボタン非表示＆赤文言）
        return redirect()
            ->to("/attendance/detail/{$att->id}")
            ->with('status', '修正申請を送信しました。承認をお待ちください。');

    }

    public function upsertByDate(AttendanceFormRequest $request, string $ymd)
    {
        // 1) 日付を正規化
        $date = \Carbon\Carbon::parse($ymd)->toDateString();

        // 2) その日の Attendance を作成（なければ）※実績ゼロでも申請の“台”を作る
        $att = Attendance::firstOrCreate(
            ['user_id' => auth()->id(), 'work_date' => $date],
            ['clock_in_at' => null, 'clock_out_at' => null] // 既定値があればここに
        );

        // 3) 既に pending 申請があれば、そのまま詳細へ（連打対策）
        if (optional($att->request)->status === AttendanceRequest::STATUS_PENDING) {
            return redirect("/attendance/detail/{$att->id}");
        }

        // 4) 修正申請を起票（元データはまだ確定変更しない）
        DB::transaction(function () use ($request, $att) {
            AttendanceRequest::create([
                'attendance_id' => $att->id,
                'requested_by'  => auth()->id(),
                'status'        => AttendanceRequest::STATUS_PENDING,
                'reason'        => $request->input('reason'),      // 備考
                'payload'       => [
                    'clock_in_at'  => $request->input('clock_in_at'),
                    'clock_out_at' => $request->input('clock_out_at'),
                    // breaks は [ ['start_at'=>'12:00','end_at'=>'12:30'], ... ] を想定
                    'breaks'       => array_values($request->input('breaks', [])),
                ],
            ]);
        });

        // 5) 申請中メッセージ付きで詳細へ（詳細画面は payload を優先表示 & ロック）
        return redirect("/attendance/detail/{$att->id}")
        ->with('status', '修正申請を送信しました。承認をお待ちください。');
    }
 
}

