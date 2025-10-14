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
        $attendances = Attendance::with('breaks')
            ->where('user_id', auth()->id())
            ->whereBetween('work_date', [$month, $end])
            ->get()
            ->keyBy(fn($a)=>$a->work_date->format('Y-m-d'));

        $rows = [];
        for ($d = $month->copy(); $d->lte($end); $d->addDay()) {
            $key = $d->toDateString();
            $a   = $attendances->get($key);

            // ★ その日のレコードが無ければ作って ID を確保
            if (!$a) {
                $a = Attendance::firstOrCreate([
                    'user_id'   => auth()->id(),
                    'work_date' => $key,
                ]);
                // ★ 後続の参照用にコレクションへも反映
                $a->setRelation('breaks', collect()); // 新規は空コレクションに
                $attendances->put($key, $a);
            }

            $in  = $this->min0($a?->clock_in_at);
            $out = $this->min0($a?->clock_out_at);

            // 休憩は start/end とも「分切り捨て」で差分
            $breakSec = $a
                ? $a->breaks->reduce(function ($s, $b) {
                    if ($b->start_at && $b->end_at) {
                        $st = $this->min0($b->start_at);
                        $en = $this->min0($b->end_at);
                        return $s + max(0, $en->diffInSeconds($st));
                    }
                    return $s;
                }, 0)
                : 0;

            $totalSec = ($in && $out)
                ? max(0, $out->diffInSeconds($in) - $breakSec)
                : null;

            $rows[] = [
                'id'    => $a->id ?? null,
                'ymd'   => $d->toDateString(),
                'date'  => $d->locale('ja')->isoFormat('MM/DD(dd)'),
                'in'    => $in  ? $in->format('H:i')  : '-',
                'out'   => $out ? $out->format('H:i') : '-',
                'break' => gmdate('G:i', $breakSec),                 // 0:01 など
                'total' => is_int($totalSec) ? gmdate('G:i', $totalSec) : '-',
            ];
        }

        return view('user.attendances.index', compact('month', 'rows'));
    }

    public function detail($id)
    {
        $attendance = Attendance::with(['user', 'breaks', 'requests'])
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        return view('user.attendances.detail', compact('attendance'));
    }

    public function detailByDate(string $date)
    {
        $d = Carbon::parse($date)->startOfDay(); // 例外は 404 でOK

        $attendance = Attendance::firstOrCreate([
            'user_id'   => auth()->id(),
            'work_date' => $d->toDateString(),
        ]);

        // 関連を読み直して詳細へ
        $attendance->load(['user', 'breaks' => fn($q)=>$q->orderBy('start_at')]);

        return view('user.attendances.detail', compact('attendance'));
    }



    public function store(Request $request, Attendance $attendance)
    {
        abort_if($attendance->user_id !== auth()->id(), 403);

        $data = $request->validate([
            'reason'        => ['required', 'string', 'max:2000'],
            'clock_in_at'   => ['nullable', 'date'],
            'clock_out_at'  => ['nullable', 'date', 'after_or_equal:clock_in_at'],
            'breaks'        => ['nullable', 'array'],
            'breaks.*.start_at' => ['nullable', 'date'],
            'breaks.*.end_at'   => ['nullable', 'date','after:breaks.*.start_at'],
        ]);

        $payload = [
            'clock_in_at'  => $data['clock_in_at'] ?? null,
            'clock_out_at' => $data['clock_out_at'] ?? null,
            'breaks'       => $data['breaks'] ?? [],
        ];

        DB::transaction(function () use ($attendance, $payload, $data) {
            AttendanceRequest::create([
                'attendance_id' => $attendance->id,
                'requested_by'  => auth()->id(),
                'status'        => AttendanceRequest::STATUS_PENDING,
                'reason'        => $data['reason'],
                'payload'       => $payload,
            ]);
        });

        return redirect('/requests')->with('status', '修正申請を送信しました');
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