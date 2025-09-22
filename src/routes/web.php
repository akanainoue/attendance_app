<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

use \App\Http\Controllers\PunchController;
use \App\Http\Controllers\StaffAttendanceController;
use \App\Http\Controllers\StaffRequestController;
use \App\Http\Controllers\AdminAttendanceController;
use \App\Http\Controllers\AdminRequestController;
use \App\Http\Controllers\Admin\Auth\AuthenticatedSessionController;
use Laravel\Fortify\Fortify;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

// --- ユーザー側は Fortify にお任せ（/login, /register, /logout は自動登録） ---
Route::middleware(['auth','verified'])->group(function () {
     // 打刻（出勤画面）
    Route::get('/attendance', [PunchController::class, 'show']);
    Route::post('/attendance/clock-in',  [PunchController::class, 'clockIn']);
    Route::post('/attendance/clock-out', [PunchController::class, 'clockOut']);
    Route::post('/attendance/break-start', [PunchController::class, 'breakStart']);
    Route::post('/attendance/break-end',   [PunchController::class, 'breakEnd']);

    // 勤怠一覧・詳細・備考・申請
    Route::get('/attendance/list',               [StaffAttendanceController::class, 'index']);        // 勤怠一覧
    Route::get('/attendance/detail/{id}',        [StaffAttendanceController::class, 'detail']);         // 勤怠詳細
    Route::get('/attendance/detail/date/{date}',
    [StaffAttendanceController::class, 'detailByDate'])->where('date', '\d{4}-\d{2}-\d{2}');
    Route::post('/attendance/detail/{id}/notes', [StaffRequestController::class, 'upsert']);      //備考
    Route::get('/stamp_correction_request/list', [StaffAttendanceController::class, 'requestIndex']); // 申請一覧
});

// --- 管理者用: 画面は自前、処理は Fortify に渡す ---
Route::prefix('admin')->group(function () {
    // ログイン画面（ビュー返すだけ）
    Route::get('/login', [\App\Http\Controllers\Admin\Auth\AuthenticatedSessionController::class, 'create'])
        ->middleware('guest:admin');

    // ログイン処理：Fortify に渡す（admin ガードで）
    Route::post('/login', [\Laravel\Fortify\Http\Controllers\AuthenticatedSessionController::class, 'store'])
        ->middleware(['web', 'guest:admin', 'fortify.guard:admin']);

    // ログアウト処理：Fortify に渡す（admin ガードで）
    Route::post('/logout', [\Laravel\Fortify\Http\Controllers\AuthenticatedSessionController::class, 'destroy'])
        ->middleware(['web', 'auth:admin', 'fortify.guard:admin']);

    // 管理者の保護エリア（例）
    Route::middleware('auth:admin')->group(function () {
        Route::get('/attendance/list',              [AdminAttendanceController::class, 'index']);
        Route::get('/attendance/{id}',         [AdminAttendanceController::class, 'detail']);
        Route::get('/staff/list',                    [AdminAttendanceController::class, 'staffIndex']);
        Route::get('/attendance/staff/{id}', [AdminAttendanceController::class, 'indexByStaff']);

        Route::get('/stamp_correction_request/list',                 [AdminRequestController::class, 'requestIndex']);
        Route::get('/stamp_correction_request/approve/{attendance_correct_request}',            [AdminRequestController::class, 'showRequest']);
        Route::patch('/requests/{id}/accept',   [AdminRequestController::class, 'accept']);
    });
});