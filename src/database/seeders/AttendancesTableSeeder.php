<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Attendance;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AttendancesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $users  = User::all();
        $today  = today();
        $start  = $today->copy()->subDays(20); // 直近20日ぶん作成

        foreach ($users as $user) {
            for ($d = $start->copy(); $d->lte($today); $d->addDay()) {

                // たまに欠勤（25%）
                if (rand(1, 100) <= 25) {
                    continue;
                }

                // たまに土日を休みにする（50%）
                if (in_array($d->dayOfWeek, [Carbon::SATURDAY, Carbon::SUNDAY]) && rand(0, 1)) {
                    continue;
                }

                DB::transaction(function () use ($user, $d) {
                    $workDate = $d->toDateString();

                    // 出勤時間（8:00〜10:30）
                    $inHour = rand(8, 10);
                    $inMin  = [0, 15, 30, 45][array_rand([0, 1, 2, 3])];
                    $clockIn = Carbon::create($d->year, $d->month, $d->day, $inHour, $inMin);

                    // 退勤時間（出勤+8〜10時間）
                    $outHour = $inHour + 8 + rand(0, 2);
                    $outMin  = [0, 10, 20, 30, 40, 50][array_rand([0, 1, 2, 3, 4, 5])];
                    $clockOut = Carbon::create($d->year, $d->month, $d->day, $outHour, $outMin);

                    // 勤怠本体（同日の既存があれば更新）
                    $attendance = Attendance::updateOrCreate(
                        ['user_id' => $user->id, 'work_date' => $workDate],
                        ['clock_in_at' => $clockIn, 'clock_out_at' => $clockOut]
                    );

                    // 既存の休憩は作り直す
                    $attendance->breaks()->delete();

                    // 休憩 0〜2 回
                    $breakCount = rand(0, 2);
                    for ($i = 0; $i < $breakCount; $i++) {
                        // 出勤から2〜4時間後に休憩開始
                        $startAt = (clone $clockIn)->addHours(rand(2, 4))->addMinutes(rand(0, 50));
                        if ($startAt->gte($clockOut)) {
                            $startAt = (clone $clockIn)->addHours(2);
                        }

                        // 10〜60分の休憩
                        $endAt = (clone $startAt)->addMinutes(rand(10, 60));
                        if ($endAt->gt($clockOut)) {
                            $endAt = (clone $clockOut)->subMinutes(rand(5, 20));
                        }

                        $attendance->breaks()->create([
                            'start_at' => $startAt,
                            'end_at'   => $endAt,
                        ]);
                    }
                });
            }
        }
    }
}
