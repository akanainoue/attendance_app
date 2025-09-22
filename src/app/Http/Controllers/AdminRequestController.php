<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
// use App\Models\Attendance;
use App\Models\AttendanceRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AdminRequestController extends Controller
{
    /**
     * 申請一覧（承認待ち/承認済みタブ）
     * GET /admin/stamp_correction_request/list?tab=pending|approved|rejected
     */
    public function index(Request $request)
    {
        $tab = $request->query('tab', AttendanceRequest::STATUS_PENDING);

        $requests = AttendanceRequest::with(['attendance.user'])
            ->when($tab, fn ($q) => $q->where('status', $tab))
            ->latest()->paginate(50);

        return view('admin.requests.index', compact('requests', 'tab'));
    }

    /**
     * 申請詳細（承認画面）
     * GET /admin/stamp_correction_request/approve/{id}
     */
    public function show($id)
    {
        $req = AttendanceRequest::with(['attendance.user'])->findOrFail($id);
        return view('admin.requests.detail', compact('req'));
    }

    /**
     * 承認（payloadを実データに反映）
     * PATCH /admin/requests/{id}/accept
     */
    public function accept($id)
    {
        $req = AttendanceRequest::with('attendance.breaks')->findOrFail($id);
        abort_if($req->status !== AttendanceRequest::STATUS_PENDING, 422, '処理済みの申請です。');

        DB::transaction(function () use ($req) {
            $payload = $req->payload ?? [];
            /** @var Attendance $att */
            $att = $req->attendance()->lockForUpdate()->first();

            // 勤怠本体
            if (!empty($payload['clock_in_at'])) {
                $att->clock_in_at = Carbon::parse($payload['clock_in_at']);
            }
            if (!empty($payload['clock_out_at'])) {
                $att->clock_out_at = Carbon::parse($payload['clock_out_at']);
            }
            $att->save();

            // 休憩（置き換え）
            if (isset($payload['breaks']) && is_array($payload['breaks'])) {
                $att->breaks()->delete();
                foreach ($payload['breaks'] as $br) {
                    if (empty($br['start_at'])) continue;
                    $att->breaks()->create([
                        'start_at' => Carbon::parse($br['start_at']),
                        'end_at'   => !empty($br['end_at']) ? Carbon::parse($br['end_at']) : null,
                    ]);
                }
            }

            // 申請の状態を更新
            $req->update([
                'status'      => AttendanceRequest::STATUS_APPROVED,
                'reviewed_by' => Auth::guard('admin')->id(),
                'reviewed_at' => now(),
            ]);
        });

        return redirect('/admin/stamp_correction_request/list?tab=approved')
            ->with('status', '申請を承認しました');
    }
}