<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Attendance;
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
        $date = $request->query('date', now()->toDateString());

        $attendances = Attendance::with(['user','breaks'])
            ->forDate($date)
            ->orderByRelation('user.name') // Laravel8ではorderByRelationは無いので下で並べ替え
            ->get()
            ->sortBy(fn($a) => $a->user->name);

        $d = Carbon::parse($date);
        return view('admin.attendances.index', [
            'attendances' => $attendances,
            'date'        => $d,                         // 画面の「前日/翌日」ボタン用
            'prev'        => $d->copy()->subDay()->toDateString(),
            'next'        => $d->copy()->addDay()->toDateString(),
        ]);
    }


    /**
     * 勤怠詳細（1レコード）
     * GET /admin/attendance/{id}
     */
    public function detail($id)
    {
        $attendance = Attendance::with(['user','breaks','notes.author'])->findOrFail($id);
        return view('admin.attendances.detail', compact('attendance'));
    }

    public function staffIndex(Request $request)
    {
        $keyword = $request->query('q');
        $users = User::when($keyword, function ($q) use ($keyword) {
                $q->where('name','like',"%{$keyword}%")->orWhere('email','like',"%{$keyword}%");
            })
            ->orderBy('name')
            ->paginate(50);

        return view('admin.users.index', compact('users','keyword'));
    }

    public function indexByUser($id, Request $request)
    {
        $user = User::findOrFail($id);
        $month = $request->query('month', now()->format('Y-m'));

        $attendances = Attendance::with('breaks')
            ->where('user_id', $user->id)
            ->forMonth($month)
            ->orderBy('work_date')
            ->get();

        $start = Carbon::parse($month.'-01')->startOfMonth();
        $end   = (clone $start)->endOfMonth();

        return view('admin.attendances.by_user', compact('user','attendances','start','end'));
    }
}
