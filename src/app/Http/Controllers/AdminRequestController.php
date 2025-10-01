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
    // public function index(Request $request)
    // {
    //     $tab = $request->query('tab', AttendanceRequest::STATUS_PENDING);

    //     $requests = AttendanceRequest::with(['attendance.user'])
    //         ->when($tab, fn ($q) => $q->where('status', $tab))
    //         ->latest()->paginate(50);

    //     return view('admin.requests.index', compact('requests', 'tab'));
    // }

    /**
     * 申請一覧（承認待ち / 承認済みタブ）
     * GET /admin/stamp_correction_request/list?status=pending|approved
     */
    public function requestIndex(Request $request)
    {
        // タブ状態（デフォルト: 承認待ち）
        $status = $request->query('status', 'pending'); // 'pending' or 'approved'

        $query = AttendanceRequest::with([
            'requester:id,name',          // 申請者（users）
            'attendance:id,user_id,work_date'
        ])->latest('created_at');

        if ($status === 'approved') {
            $query->where('status', AttendanceRequest::STATUS_APPROVED);
        } else {
            $query->where('status', AttendanceRequest::STATUS_PENDING);
            $status = 'pending';
        }

        $items = $query->get();

        // Blade へ渡す配列に整形（画像どおりの列）
        $rows = $items->map(function (AttendanceRequest $r) {
            $workDate = $r->attendance?->work_date;

            // work_date のキャストが date でない場合に備えた保険
            if ($workDate && !($workDate instanceof Carbon)) {
                $workDate = Carbon::parse($workDate);
            }

            return [
                'id'      => $r->id,
                'status'  => $r->status === AttendanceRequest::STATUS_APPROVED ? '承認済み' : '承認待ち',
                'name'    => $r->requestedBy?->name ?? '―',
                'target'  => $workDate ? $workDate->format('Y/m/d') : '―',
                'reason'  => $r->reason ?? '―',
                'applied' => $r->created_at?->format('Y/m/d') ?? '―',
            ];
        })->all();

        return view('admin.requests.index', compact('rows', 'status'));
    }



    /**
     * 申請詳細（承認画面）
     * GET /admin/stamp_correction_request/approve/{attendance_correct_request}
     */
    public function showRequest($attendance_correct_request)
    {
        // 申請＋関連（attendance.user / attendance.breaks）を取得
        $req = AttendanceRequest::with([
                'attendance.user',
                'attendance.breaks' => fn($q) => $q->orderBy('start_at'),
            ])->findOrFail($attendance_correct_request);

        $att  = $req->attendance;
        $user = $att?->user;

        // フォーマッタ（文字列/Carbon/null を安全に H:i へ）
        $fmt = function ($dt) {
            if (empty($dt)) return '-';
            return ($dt instanceof Carbon)
                ? $dt->format('H:i')
                : Carbon::parse($dt)->format('H:i');
        };

        // 勤務日
        $work = $att?->work_date;
        $workC = $work instanceof Carbon ? $work : ($work ? Carbon::parse($work) : null);

        // 休憩（最大2本想定）
        $breaks = $att?->breaks ?? collect();
        $b1 = $breaks->get(0);
        $b2 = $breaks->get(1);

        // ビューへ
        return view('admin.requests.review', [
            'requestId' => $req->id,
            'approved'  => $req->status === 'approved', // ステータスは実テーブルの値に合わせて

            'name'      => $user?->name ?? '—',
            'dateYear'  => $workC?->format('Y') ?? '',
            'dateMd'    => $workC?->format('n月j日') ?? '',

            'in'        => $fmt($att?->clock_in_at),
            'out'       => $fmt($att?->clock_out_at),
            'b1s'       => $fmt(optional($b1)->start_at),
            'b1e'       => $fmt(optional($b1)->end_at),
            'b2s'       => $fmt(optional($b2)->start_at),
            'b2e'       => $fmt(optional($b2)->end_at),

            'note'      => $req->reason ?? '—',
        ]);
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