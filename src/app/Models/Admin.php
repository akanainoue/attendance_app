<?php

namespace App\Models;

// use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Illuminate\Database\Eloquent\Model;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

// class Admin extends Model
// {
//     use HasFactory;
// }

class Admin extends Authenticatable
{
    use Notifiable;

    protected $fillable = ['name','email','password'];
    protected $hidden = ['password','remember_token'];
    
    // 自分が審査した申請
    public function reviewedRequests() { return $this->hasMany(CorrectionRequest::class,'reviewed_by'); }
}