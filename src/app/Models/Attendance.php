<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'work_date',
        'clock_in_at',
        'clock_out_at'
    ];
    protected $casts = [
        'work_date'=>'date',
        'clock_in_at'=>'datetime',
        'clock_out_at'=>'datetime'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function breaks()
    {
        return $this->hasMany(WorkBreak::class,'attendance_id');
    }

    public function requests()
    {
        return $this->hasMany(AttendanceRequest::class);
    }


    public function request()
    {
        return $this->hasOne(AttendanceRequest::class, 'attendance_id');
    }

    // 今のステータス（画面の表示切替に利用）
    public function status(): string {
        if (is_null($this->clock_in_at)) return 'off';                 // 勤務外
        if ($this->clock_out_at) return 'done';                        // 退勤済
        $onBreak = $this->breaks()->whereNull('end_at')->exists();
        return $onBreak ? 'breaking' : 'working';                      // 休憩中 / 出勤中
    }

    /** 休憩の合計秒数 */
    public function getBreakSecondsAttribute(): int
    {
        return (int) $this->breaks->sum(
            fn ($b) => $b->end_at ? $b->end_at->diffInSeconds($b->start_at) : 0
        );
    }

    /** 実働の合計秒数 = (退勤-出勤) - 休憩 */
    public function getWorkSecondsAttribute(): ?int
    {
        if (! $this->clock_in_at || ! $this->clock_out_at) {
            return null;
        }
        return max(0, $this->clock_out_at->diffInSeconds($this->clock_in_at) - $this->break_seconds);
    }

    /** 表示用：休憩 "G:i" */
    public function getBreakHmAttribute(): string
    {
        return gmdate('G:i', $this->break_seconds);
    }

    /** 表示用：実働 "G:i"（未確定なら null） */
    public function getWorkHmAttribute(): ?string
    {
        return is_null($this->work_seconds) ? null : gmdate('G:i', $this->work_seconds);
    }
    
    // 休憩合計（秒）
    // public function breakSeconds(): int
    // {
    //     return (int) $this->breaks
    //         ->sum(fn($b) => $b->end_at ? $b->end_at->diffInSeconds($b->start_at) : 0);
    // }

    // 実働合計（秒）: (退勤−出勤) − 休憩
    // public function workSeconds(): ?int
    // {
    //     if (!$this->clock_in_at || !$this->clock_out_at) return null;
    //     $sec = $this->clock_out_at->diffInSeconds($this->clock_in_at) - $this->breakSeconds();
    //     return max(0, $sec);
    // }

    /** 休憩合計(分) */
    // public function getBreakTotalMinutesAttribute(): int
    // {
    //     $breaks = $this->relationLoaded('breaks') ? $this->breaks : $this->breaks()->get();
    //     return (int) $breaks->sum(function ($b) {
    //         if (!$b->start_at || !$b->end_at) return 0;
    //         return $b->end_at->diffInMinutes($b->start_at);
    //     });
    // }

    /** 実労働合計(分) = (退勤-出勤) - 休憩 */
    // public function getWorkTotalMinutesAttribute(): ?int
    // {
    //     if (!$this->clock_in_at || !$this->clock_out_at) return null;
    //     return max(0, $this->clock_out_at->diffInMinutes($this->clock_in_at) - $this->break_total_minutes);
    // }

    /** 日付指定 */
    public function scopeForDate($q, $date)
    {
        $d = Carbon::parse($date)->toDateString();
        return $q->whereDate('work_date', $d);
    }

    /** YYYY-MM 月指定 */
    public function scopeForMonth($q, string $ym)
    {
        $start = Carbon::parse($ym.'-01')->startOfMonth();
        $end   = (clone $start)->endOfMonth();
        return $q->whereBetween('work_date', [$start->toDateString(), $end->toDateString()]);
    }
}
