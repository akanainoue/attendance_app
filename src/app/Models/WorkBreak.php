<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkBreak extends Model
{
    use HasFactory;
    
    protected $table='breaks';

    protected $fillable=[
        'attendance_id',
        'start_at','end_at'
    ];

    protected $casts=[
        'start_at'=>'datetime',
        'end_at'=>'datetime',
    ];

    public function attendance(){ 
        return $this->belongsTo(Attendance::class); 
    }
}

// Attendance / Break に日時キャストを付けておくと、自動で Carbon インスタンスになります。