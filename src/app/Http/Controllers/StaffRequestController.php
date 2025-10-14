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
