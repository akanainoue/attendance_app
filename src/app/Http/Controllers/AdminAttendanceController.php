<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\AttendanceRequest;
use App\Models\Attendance;
use App\Models\WorkBreak;
use App\Models\User;
use Carbon\Carbon;

class AdminAttendanceController extends Controller
{

    /**
     * 管理者の「日別勤怠一覧」
     * GET /admin/attendance/list?date=2023-06-01
     */

    public function index(Request $request)
    {
        // $date = $request->input('date', now()->toDateString());

        $date = $request->filled('date')
        ? Carbon::parse($request->date)->startOfDay()
        : Carbon::today();

        $rows = Attendance::with(['user', 'breaks'])   // 名前や休憩を使うだけなら eager load で十分
            ->whereDate('work_date', $date)
            ->get();                                   // ← 並び替えなし

        return view('admin.attendances.index', compact('rows', 'date'));
    }


    /**
     * 勤怠詳細（1レコード）
     * GET /admin/attendance/{id}
     */
    public function detail($id)
    {
        $attendance = Attendance::with(['user','breaks','request'])->findOrFail($id);
        return view('admin.attendances.detail', compact('attendance'));
    }

    public function update(AttendanceFormRequest $request, $id)
    {
        $attendance = Attendance::with(['breaks','request'])->findOrFail($id);

        // ====== 1) 勤怠（出退勤） ======
        $workDate = $attendance->work_date instanceof Carbon
                  ? $attendance->work_date->toDateString()
                  : Carbon::parse($attendance->work_date)->toDateString();

        $cin  = $this->mergeTime($workDate, $request->input('clock_in_at'));
        $cout = $this->mergeTime($workDate, $request->input('clock_out_at'));

        $attendance->update([
            'clock_in_at'  => $cin,
            'clock_out_at' => $cout,
        ]);

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
        $reason = $request->input('reason'); // ※ フォーム側は name="reason" に変更済み
        if ($reason !== null) {
            // 既存の最新申請があればそれを更新、無ければ作成（statusは既存優先）
            $current = $attendance->request; // 最新1件（latestOfMany 前提）
            if ($current) {
                $current->update([
                    'reason'  => $reason,
                    'payload' => $this->snapshot($attendance), // 任意：変更後スナップショット
                ]);
            } else {
                CorrectionRequest::create([
                    'attendance_id' => $attendance->id,
                    'requested_by'  => $attendance->user_id, // スタッフ本人名義で残す
                    'status'        => defined('App\Models\AttendanceRequest::STATUS_APPROVED')
                                        ? CorrectionRequest::STATUS_APPROVED
                                        : 'approved', // 定数が無い場合のフォールバック
                    'reason'        => $reason,
                    'payload'       => $this->snapshot($attendance),
                ]);
            }
        }

        return redirect()
            ->route('admin.attendance.detail', $attendance->id)
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



        $rows = [];
        for ($d = $month->copy(); $d->lte($end); $d->addDay()) {
            $a = $attendances->get($d->toDateString());

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
