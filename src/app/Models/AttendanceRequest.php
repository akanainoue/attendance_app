<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceRequest extends Model
{
    use HasFactory;

     // ← テーブル名は attendance_requests
    protected $table = 'attendance_requests';

    protected $fillable=[
        'attendance_id',
        'requested_by',
        'status',
        'reason',
        'payload',
        'reviewed_by',
        'reviewed_at'
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $casts=[
        'payload'=>'array','reviewed_at'=>'datetime'
    ];


    public function attendance()
    {
        return $this->belongsTo(Attendance::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class,'requested_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(Admin::class,'reviewed_by');
    }
}
