<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\AdminAttendanceUpdateRequest;
use App\Models\Attendance;
use App\Models\AttendanceRequest;
use App\Models\WorkBreak;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminAttendanceController extends Controller
{

    /**
     * 管理者の「日別勤怠一覧」
     * GET /admin/attendance/list?date=2023-06-01
     */

    public function index(Request $request)
    {
        // 表示する日（デフォルト: 今日）
        $date = Carbon::parse($request->get('date', now()->toDateString()))->toDateString();

        // 一覧に出すスタッフ（ページングや検索があれば各自の要件で）
        $users = User::orderBy('id')->get(['id','name','email']);

        // 該当日の勤怠 + 最新申請（1件）をまとめて取得
        $atts = Attendance::with([
                'breaks',
                'request'])
            ->whereDate('work_date', $date)
            ->get()
            ->keyBy('user_id');


        // 画面用の行データ
        $rows = $users->map(function ($u) use ($atts) {
            $att = $atts->get($u->id);

            $isPending = optional($att?->request)->status === \App\Models\AttendanceRequest::STATUS_PENDING;
            $payload   = $isPending ? (array) ($att->request->payload ?? []) : [];

            // 表示用 値の決定（申請中なら payload を優先）
            $in  = $isPending ? ($payload['clock_in_at']  ?? null) : optional($att?->clock_in_at)->format('H:i');
            $out = $isPending ? ($payload['clock_out_at'] ?? null) : optional($att?->clock_out_at)->format('H:i');

            // 休憩合計（申請中なら payload.breaks から、そうでなければ DB breaks から）
            $breaks = $isPending
                ? collect($payload['breaks'] ?? [])
                : optional($att?->breaks)->collect() ?? collect();

            $breakSec = $breaks->reduce(function ($s, $b) use ($isPending) {
                // payload なら 'H:i' 文字列、DBなら Carbon
                $st = $isPending ? ($b['start_at'] ?? null) : optional($b->start_at)?->format('H:i');
                $en = $isPending ? ($b['end_at']   ?? null) : optional($b->end_at)?->format('H:i');
                if (!$st || !$en) return $s;
                $stC = Carbon::parse($st)->seconds(0);
                $enC = Carbon::parse($en)->seconds(0);
                return $s + max(0, $enC->diffInSeconds($stC));
            }, 0);

            // 合計
            $total = '-';
            if ($in && $out) {
                $inC  = Carbon::parse($in)->seconds(0);
                $outC = Carbon::parse($out)->seconds(0);
                $sec  = max(0, $outC->diffInSeconds($inC) - $breakSec);
                $total = gmdate('G:i', $sec);
            }

            return [
                'user_id'     => $u->id,
                'name'        => $u->name,
                'in'          => $in  ?? '-',
                'out'         => $out ?? '-',
                'break'       => gmdate('G:i', $breakSec),
                'total'       => $total,
                'attendance_id' => $att->id ?? null,
                'is_pending'  => $isPending,
            ];
        });

        return view('admin.attendances.index', [
            'date' => Carbon::parse($date),
            'rows' => $rows,
        ]);
    }

    /**
     * 勤怠詳細（1レコード）
     * GET /admin/attendance/{id}
     */
    public function detail($id)
    {
        $attendance = Attendance::with(['user','breaks','request'])->findOrFail($id);
        // pending のときだけ payload を適用
        $payload = ($attendance->request
            && $attendance->request->status === AttendanceRequest::STATUS_PENDING)
            ? ($attendance->request->payload ?? [])
            : [];
        // DB側の（確定済み）値をフォールバックとして用意
        $dbBreaks = $attendance->breaks->map(fn($b) => [
            'start_at' => optional($b->start_at)->format('H:i'),
            'end_at'   => optional($b->end_at)->format('H:i'),
        ])->values()->all();

        // 画面用の値：payload を最優先 → 無ければDB値
        $form = [
            'clock_in_at'  => $payload['clock_in_at']  ?? optional($attendance->clock_in_at)->format('H:i'),
            'clock_out_at' => $payload['clock_out_at'] ?? optional($attendance->clock_out_at)->format('H:i'),
            'breaks'       => !empty($payload['breaks']) ? array_values($payload['breaks']) : $dbBreaks,
            'reason'       => $attendance->request?->reason ?? '',
            'is_pending'   => $attendance->request?->status === AttendanceRequest::STATUS_PENDING,
        ];

        // 休憩は2本分確保（不足は null ）
        while (count($form['breaks']) < 2) {
            $form['breaks'][] = ['start_at' => null, 'end_at' => null];
        }
        return view('admin.attendances.detail', compact('attendance','form'));
    }

    public function update(AdminAttendanceUpdateRequest $request, $id)
    {
        $attendance = Attendance::with(['breaks','request'])->findOrFail($id);

        // ====== 1) 勤怠（出退勤） ======
        $workDate = $attendance->work_date instanceof Carbon
                  ? $attendance->work_date->toDateString()
                  : Carbon::parse($attendance->work_date)->toDateString();

        $cin  = $this->mergeTime($workDate, $request->input('clock_in_at'));
        $cout = $this->mergeTime($workDate, $request->input('clock_out_at'));

        DB::transaction(function () use ($attendance, $cin, $cout, $request, $workDate) {
            // 一括代入で弾かれるケースを避けるため、プロパティ代入 + save に変更
            $attendance->clock_in_at  = $cin;
            $attendance->clock_out_at = $cout;
            $attendance->save();

            // ====== 2) 休憩（最大2本、両端ありのみ保存） ======
            $attendance->breaks()->delete();
            foreach (array_values($request->input('breaks', [])) as $b) {
                $s = $this->mergeTime($workDate, $b['start_at'] ?? null);
                $e = $this->mergeTime($workDate, $b['end_at']   ?? null);
                if ($s && $e) {
                    $attendance->breaks()->create([
                        'start_at' => $s,
                        'end_at'   => $e,
                    ]);
                }
            }

            // ====== 3) 備考（reason） ======
            $reason = $request->input('note'); // ※ フォーム側は name="reason" に変更済み
            if ($reason !== null) {
                // 既存の最新申請があればそれを更新、無ければ作成（statusは既存優先）
                $current = $attendance->request; // 最新1件（latestOfMany 前提）
                if ($current) {
                    $current->update([
                        'reason'  => $reason,
                        'payload' => $this->snapshot($attendance), // 任意：変更後スナップショット
                    ]);
                } else {
                    AttendanceRequest::create([
                        'attendance_id' => $attendance->id,
                        'requested_by'  => $attendance->user_id, // スタッフ本人名義で残す
                        'status'        => AttendanceRequest::STATUS_APPROVED, // 直接確定扱いなら
                        'reason'        => $reason,
                        'payload'       => $this->snapshot($attendance),
                    ]);
                }
            }
        });

        return redirect("/admin/attendance/{$attendance->id}")
            ->with('status', '勤怠を更新しました。');
    }

    /** 'Y-m-d' + 'H:i' → Carbon（null は null） */
    private function mergeTime(string $ymd, ?string $hm): ?Carbon
    {
        if (!$hm) return null;
        return Carbon::parse("$ymd $hm", config('app.timezone'));
    }

    /** 任意：現時点の勤怠スナップショットをJSONで保存（監査用） */
    private function snapshot(Attendance $att): array
    {
        return [
            'clock_in_at'  => optional($att->clock_in_at)->format('Y-m-d H:i:s'),
            'clock_out_at' => optional($att->clock_out_at)->format('Y-m-d H:i:s'),
            'breaks'       => $att->breaks()->orderBy('start_at')->get()
                                ->map(fn($b)=>[
                                    'start_at'=>optional($b->start_at)->format('Y-m-d H:i:s'),
                                    'end_at'  =>optional($b->end_at)->format('Y-m-d H:i:s'),
                                ])->values()->toArray(),
            'saved_at'     => now()->format('Y-m-d H:i:s'),
            'by'           => 'admin_direct',
        ];
    }

    public function staffIndex()
    {
        $staffs = User::query()
            ->select(['id', 'name', 'email'])
            ->orderBy('name')
            ->get();

        return view('admin.staff.index', compact('staffs'));
    }

    public function indexByStaff(Request $request, int $id)
    {
        $user   = User::findOrFail($id);
        $monthY = $request->get('month', now()->format('Y-m'));
        $month  = Carbon::parse($monthY . '-01')->startOfMonth();
        $end    = $month->copy()->endOfMonth();

        $attendances = Attendance::with('breaks')
            ->where('user_id', $user->id)
            ->whereBetween('work_date', [$month->toDateString(), $end->toDateString()])
            ->get()
            ->keyBy(fn ($a) => $a->work_date->toDateString());

        // ① 当月の「承認待ち申請」を日付キーで取得（対象ユーザー分）
        $pendingByDate = AttendanceRequest::with('attendance')
            ->where('status', AttendanceRequest::STATUS_PENDING)
            ->whereHas('attendance', function ($q) use ($user, $month, $end) {
                $q->where('user_id', $user->id)
                  ->whereBetween('work_date', [$month->toDateString(), $end->toDateString()]);
            })
            ->get()
            ->keyBy(function ($r) {
                // attendance が必ずある前提（※無ければ upsertByDate で作る設計に）
                return optional($r->attendance->work_date)->toDateString();
            });

        
        // ② 行の合成（実績が無ければペンディング申請から作る）
        $rows = [];
        for ($d = $month->copy(); $d->lte($end); $d->addDay()) {
            $ymd = $d->toDateString();
        
            /** @var \App\Models\Attendance|null $a */
            $a = $attendances->get($ymd);
        
            /** @var \App\Models\AttendanceRequest|null $req */
            $req = $pendingByDate->get($ymd);
            $payload = $req?->payload ?? [];   // ['clock_in_at'=>'09:00', 'clock_out_at'=>'18:00', 'breaks'=>[...]] を想定
        
            // 表示用の in/out を「申請があれば申請値、なければ実績」で決める
            $in  = $payload['clock_in_at']  ?? optional($a?->clock_in_at)->format('H:i');
            $out = $payload['clock_out_at'] ?? optional($a?->clock_out_at)->format('H:i');
        
            // 休憩の秒数（payload があれば payload から、無ければ DB の breaks から）
            $breakSec = 0;
            if (!empty($payload['breaks'])) {
                foreach ($payload['breaks'] as $b) {
                    $s = isset($b['start_at']) ? Carbon::parse("{$ymd} {$b['start_at']}") : null;
                    $e = isset($b['end_at'])   ? Carbon::parse("{$ymd} {$b['end_at']}")   : null;
                    if ($s && $e) $breakSec += max(0, $e->diffInSeconds($s));
                }
            } elseif ($a) {
                $breakSec = $a->breaks->reduce(function ($sum, $br) {
                    if ($br->start_at && $br->end_at) {
                        $st = $br->start_at->copy()->seconds(0);
                        $en = $br->end_at->copy()->seconds(0);
                        return $sum + max(0, $en->diffInSeconds($st));
                    }
                    return $sum;
                }, 0);
            }
        
            // 合計時間（in/out が両方あるときだけ計算）
            $total = '-';
            if ($in && $out) {
                $cin  = Carbon::parse("{$ymd} {$in}");
                $cout = Carbon::parse("{$ymd} {$out}");
                $sec  = max(0, $cout->diffInSeconds($cin) - $breakSec);
                $total = gmdate('G:i', $sec);
            }
        
            $rows[] = [
                'id'      => $a?->id,                              // 実績が無ければ null
                'ymd'     => $ymd,
                'date'    => $d->locale('ja')->isoFormat('MM/DD(dd)'),
                'in'      => $in ?: '-',
                'out'     => $out ?: '-',
                'break'   => gmdate('G:i', $breakSec),
                'total'   => $total,
                'pending' => (bool) $req,                          // ← 申請中バッジ用フラグ
            ];
        }


        // CSV 出力
        if ($request->get('export') === 'csv') {
            $filename = sprintf('%s_%s.csv', $user->name, $month->format('Y-m'));
            return $this->exportCsv($filename, $rows);
        }

        return view('admin.staff-attendances.index', compact('user', 'month', 'rows'));
    }

    /* ----------------- helpers ----------------- */

    private function exportCsv(string $filename, array $rows): StreamedResponse
    {
        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename*=UTF-8''".rawurlencode($filename),
        ];

        return new StreamedResponse(function () use ($rows) {
            $out = fopen('php://output', 'w');
            // Excel向けに UTF-8 BOM
            fwrite($out, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($out, ['日付','出勤','退勤','休憩','合計']);
            foreach ($rows as $r) {
                fputcsv($out, [$r['date'], $r['in'], $r['out'], $r['break'], $r['total']]);
            }
            fclose($out);
        }, 200, $headers);
    }

    private function min0(?\Carbon\Carbon $dt): ?\Carbon\Carbon
    {
        return $dt?->copy()->seconds(0);   // 秒を 0 に
    }
}

