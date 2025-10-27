<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\User;
use App\Models\WorkBreak;
use App\Models\AttendanceRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
// use Carbon\Carbon;

class StaffAttendanceController extends Controller
{
    public function index(Request $request)
    {
        $month = \Carbon\Carbon::parse($request->get('month', now()->format('Y-m')))->startOfMonth();
        $end   = $month->copy()->endOfMonth();

        // 1日〜月末まで行を作り、該当日の勤怠があれば埋める想定
        $attendances = Attendance::with(['breaks', 'request'])
            ->where('user_id', auth()->id())
            ->whereBetween('work_date', [$month, $end])
            ->get()
            ->keyBy(fn($a)=>$a->work_date->format('Y-m-d'));

        $rows = [];
        for ($d = $month->copy(); $d->lte($end); $d->addDay()) {
            /** @var \App\Models\Attendance|null $a */
            $a = $attendances->get($d->toDateString());

            // ① pending の申請payloadがあれば、それを一覧の表示値として“適用”
            $payload = ($a && $a->request && $a->request->status === \App\Models\AttendanceRequest::STATUS_PENDING)
                ? ($a->request->payload ?? [])
                : [];

            // ② 出退勤の「見かけの値」を作る（H:i）
            $inStr  = $payload['clock_in_at']  ?? optional($a?->clock_in_at)->format('H:i');
            $outStr = $payload['clock_out_at'] ?? optional($a?->clock_out_at)->format('H:i');

            // ③ 休憩の「見かけの値」リスト（payload優先、なければDB）
            $brPayload = collect($payload['breaks'] ?? []);
            $brDb = collect($a?->breaks ?? [])->map(fn($b)=>[
                'start_at' => optional($b->start_at)->format('H:i'),
                'end_at'   => optional($b->end_at)->format('H:i'),
            ]);
            $brList = $brPayload->isNotEmpty() ? $brPayload : $brDb;

            // ④ 合計を計算（分は切り捨て・秒は持たない想定）
            $in  = $inStr  ? \Carbon\Carbon::parse($d->toDateString().' '.$inStr)  : null;
            $out = $outStr ? \Carbon\Carbon::parse($d->toDateString().' '.$outStr) : null;

            // 休憩秒
            $breakSec = $brList->reduce(function ($s, $b) use ($d) {
                $sStr = $b['start_at'] ?? null;
                $eStr = $b['end_at']   ?? null;
                if ($sStr && $eStr) {
                    $st = \Carbon\Carbon::parse($d->toDateString().' '.$sStr)->seconds(0);
                    $en = \Carbon\Carbon::parse($d->toDateString().' '.$eStr)->seconds(0);
                    return $s + max(0, $en->diffInSeconds($st));
                }
                return $s;
            }, 0);

            $totalSec = ($in && $out) ? max(0, $out->seconds(0)->diffInSeconds($in->seconds(0)) - $breakSec) : null;

            $rows[] = [
                'id'    => $a->id ?? null,
                'ymd'   => $d->toDateString(),
                'date'  => $d->locale('ja')->isoFormat('MM/DD(dd)'),
                'in'    => $inStr  ?: '-',
                'out'   => $outStr ?: '-',
                'break' => gmdate('G:i', $breakSec),                             // 0:00 形式
                'total' => is_int($totalSec) ? gmdate('G:i', $totalSec) : '-',
                // お好みで “申請中” バッジに使えるフラグも渡せる
                'is_pending' => ($a && $a->request && $a->request->status === \App\Models\AttendanceRequest::STATUS_PENDING),
            ];
        }

        return view('user.attendances.index', compact('month', 'rows'));
    }

    public function detail($id)
    {
        $attendance = Attendance::with(['user','breaks', 'request'])->where('user_id', auth()->id())->findOrFail($id);

        $pending = $attendance->request && $attendance->request->status === AttendanceRequest::STATUS_PENDING;

        // —— 表示用フォーム値（pendingならpayload、なければDB）——
        $payload = $pending ? ($attendance->request->payload ?? []) : [];

          // DB値（修正前）をまず作る
        $dbBreaks = $attendance->breaks->map(fn($b)=>[
            'start_at' => optional($b->start_at)->format('H:i'),
            'end_at'   => optional($b->end_at)->format('H:i'),
        ])->values()->all();
        
        // フォーム値（payload を最優先 → なければ現在の打刻）
        $form = [
            'clock_in_at'  => $payload['clock_in_at']  ?? optional($attendance->clock_in_at)->format('H:i'),
            'clock_out_at' => $payload['clock_out_at'] ?? optional($attendance->clock_out_at)->format('H:i'),
            'breaks'       =>  !empty($payload['breaks']) ? array_values($payload['breaks']) : $dbBreaks,
            'reason'       => $attendance->request?->reason ?? '',
            'is_locked'    => $pending,
            // 'is_pending'   => $attendance->request?->status === \App\Models\AttendanceRequest::STATUS_PENDING,
        ];

        // 休憩は常に2本分用意（不足は null で埋める）
        while (count($form['breaks']) < 2) {
            $form['breaks'][] = ['start_at' => null, 'end_at' => null];
        }
    
        return view('user.attendances.detail', compact('attendance', 'form'));
    }


    /** 申請一覧 */
    public function requestIndex(Request $request)
    {
        // タブ（承認待ち or 承認済み）
        $tab = $request->get('tab', 'pending');
        $status = $tab === 'approved'
            ? AttendanceRequest::STATUS_APPROVED
            : AttendanceRequest::STATUS_PENDING;

        // 自分が出した申請のみ（勤怠と申請者を一緒にロード）
        $reqs = AttendanceRequest::with([
                'attendance:id,work_date',
                'requester:id,name',
            ])
            ->where('requested_by', auth()->id())
            ->where('status', $status)
            ->orderByDesc('created_at')
            ->get();

        // View 用の行データに整形
        $statusMap = [
            AttendanceRequest::STATUS_PENDING  => '承認待ち',
            AttendanceRequest::STATUS_APPROVED => '承認済み',
            // AttendanceRequest::STATUS_REJECTED => '却下',
        ];

        $rows = $reqs->map(function ($r) use ($statusMap) {
            return [
                'id'      => $r->attendance_id,                      // 詳細へ飛ばす用
                'status'  => $statusMap[$r->status] ?? $r->status,   // 日本語表示
                'name'    => optional($r->requester)->name ?? '-',
                'target'  => optional($r->attendance->work_date)->format('Y/m/d'),
                'reason'  => $r->reason ?? '',
                'applied' => optional($r->created_at)->format('Y/m/d'),
            ];
        })->all();

        return view('user.requests.index', compact('rows', 'tab'));
    }

    private function min0(?\Carbon\Carbon $dt): ?\Carbon\Carbon
    {
        return $dt?->copy()->seconds(0);   // 秒を 0 に
    }

}