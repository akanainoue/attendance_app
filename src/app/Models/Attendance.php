<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id','work_date',
        'clock_in_at',
        'clock_out_at'
    ];
    protected $casts = [
        'work_date'=>'date','clock_in_at'=>'datetime','clock_out_at'=>'datetime'
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
        return $this->hasMany(CorrectionRequest::class);
    }

    // 今のステータス（画面の表示切替に利用）
    public function status(): string {
        if (is_null($this->clock_in_at)) return 'off';                 // 勤務外
        if ($this->clock_out_at) return 'done';                        // 退勤済
        $onBreak = $this->breaks()->whereNull('end_at')->exists();
        return $onBreak ? 'breaking' : 'working';                      // 休憩中 / 出勤中
    }

    /** 休憩合計(分) */
    public function getBreakTotalMinutesAttribute(): int
    {
        $breaks = $this->relationLoaded('breaks') ? $this->breaks : $this->breaks()->get();
        return (int) $breaks->sum(function ($b) {
            if (!$b->start_at || !$b->end_at) return 0;
            return $b->end_at->diffInMinutes($b->start_at);
        });
    }

    /** 実労働合計(分) = (退勤-出勤) - 休憩 */
    public function getWorkTotalMinutesAttribute(): ?int
    {
        if (!$this->clock_in_at || !$this->clock_out_at) return null;
        return max(0, $this->clock_out_at->diffInMinutes($this->clock_in_at) - $this->break_total_minutes);
    }

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
