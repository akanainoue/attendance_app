<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Arr;

class AdminRequestController extends Controller
{
    
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
                'name'    => $r->requester?->name ?? '―',
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
        // 申請＋関連（ユーザー、勤怠、休憩）
        $req = AttendanceRequest::with([
            'attendance.user',
            'attendance.breaks',
        ])->findOrFail($attendance_correct_request);

        $att = $req->attendance; // 勤怠レコード（存在しないケースにも一応備える）

        // === 日付の決定 ===
        $ymd  = optional($att?->work_date)?->toDateString()
              ?: Arr::get($req->payload, 'date')                 // payloadにdateを入れている場合
              ?: $req->created_at->toDateString();               // 最後の保険
        $date = Carbon::parse($ymd);

        // === フォーマッタ（常に H:i を返す） ===
        $hm = function ($v) {
            if (!$v) return '—';
            if (is_string($v) && preg_match('/^\d{2}:\d{2}$/', $v)) {
                return $v; // 既に H:i
            }
            try {
                return Carbon::parse($v)->format('H:i');
            } catch (\Throwable $e) {
                return '—';
            }
        };

        // === DB側の現在値（フォールバック） ===
        $dbIn  = $hm($att?->clock_in_at);
        $dbOut = $hm($att?->clock_out_at);

        $dbBreaks = $att
            ? $att->breaks->map(fn ($b) => [
                'start_at' => $hm($b->start_at),
                'end_at'   => $hm($b->end_at),
            ])->values()->all()
            : [];

        // === payload 優先で表示値を作る（なければDB値） ===
        $payload = (array) ($req->payload ?? []);
        $pBreaks = Arr::get($payload, 'breaks', []);

        $in  = $hm(Arr::get($payload, 'clock_in_at',  $dbIn));
        $out = $hm(Arr::get($payload, 'clock_out_at', $dbOut));

        $breaks = $pBreaks ? array_values($pBreaks) : $dbBreaks;
        // 休憩は2本分に揃える
        while (count($breaks) < 2) $breaks[] = ['start_at' => null, 'end_at' => null];

        $b1s = $hm($breaks[0]['start_at'] ?? null);
        $b1e = $hm($breaks[0]['end_at']   ?? null);
        $b2s = $hm($breaks[1]['start_at'] ?? null);
        $b2e = $hm($breaks[1]['end_at']   ?? null);

        // 名前
        $name = $att?->user?->name ?? '—';

        return view('admin.requests.review', [
            'requestId' => $req->id,
            'approved'  => $req->status === AttendanceRequest::STATUS_APPROVED,
            'name'      => $name,
            'dateYear'  => $date->format('Y'),
            'dateMd'    => $date->format('n月j日'),
            'in'        => $in,
            'out'       => $out,
            'b1s'       => $b1s,
            'b1e'       => $b1e,
            'b2s'       => $b2s,
            'b2e'       => $b2e,
            'note'      => $req->reason ?? '',
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
