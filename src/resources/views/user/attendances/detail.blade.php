@extends('layouts.app')

@section('css')
<link rel="stylesheet" href="{{ asset('css/attendance-detail.css') }}">
@endsection

@section('nav')
<nav class="nav">
    <a href="{{ url('/attendance') }}">勤怠</a>
    <a href="{{ url('/attendance/list') }}">勤怠一覧</a>
    <a href="{{ url('/stamp_correction_request/list') }}">申請</a>
    <form method="POST" action="{{ url('/logout') }}" class="logout-form">
        @csrf
        <button type="submit">ログアウト</button>
    </form>
</nav>
@endsection

@section('content')
<main class="detail-main">
    <div class="container">
    <h1 class="page-title">勤怠詳細</h1>

    @php
        $req = $attendance->request ?? null;
        // 申請ステータスが pending なら編集ロック
        $isLocked = $req && (($req->status ?? '') === 'pending');

        // DB値（最終フォールバック）
        $dbIn  = optional($attendance->clock_in_at)->format('H:i');
        $dbOut = optional($attendance->clock_out_at)->format('H:i');
        $dbB0S = optional($attendance->breaks[0]->start_at ?? null)->format('H:i');
        $dbB0E = optional($attendance->breaks[0]->end_at   ?? null)->format('H:i');
        $dbB1S = optional($attendance->breaks[1]->start_at ?? null)->format('H:i');
        $dbB1E = optional($attendance->breaks[1]->end_at   ?? null)->format('H:i');

        $d = \Illuminate\Support\Carbon::parse($attendance->work_date);
    @endphp

    {{-- 更新フォーム（名前や日付は表示のみ。時間・備考は編集可） --}}
    <form method="POST" action="{{ url('/attendance/detail/'.$attendance->id.'/notes') }}" class="detail-card card {{ $isLocked ? 'form-locked' : '' }}">
        @csrf

        <div class="row">
            <div class="label">名前</div>
            <div class="value mono">
                {{ $attendance->user->name ?? '—' }}
            </div>
        </div>

        <div class="row">
            <div class="label">日付</div>
            <div class="value date-split">
                <span class="year">{{ $d->format('Y') }}年</span>
                <span class="md">{{ $d->format('n月j日') }}</span>
            </div>
        </div>

        <div class="row">
            <div class="label">出勤・退勤</div>
            <div class="value time-range">
                <input type="time" name="clock_in_at"
                    value="{{ old('clock_in_at', data_get($form ?? [], 'clock_in_at', $dbIn)) }}"
                    class="time-input" {{ $isLocked ? 'disabled' : '' }}>
                <span class="sep">〜</span>
                <input type="time" name="clock_out_at"
                    value="{{ old('clock_out_at', data_get($form ?? [], 'clock_out_at', $dbOut)) }}"
                    class="time-input" {{ $isLocked ? 'disabled' : '' }}>
                @if ($errors->has('work_time'))
                    <p class="field-error">{{ $errors->first('work_time') }}</p>
                @endif
            </div>
        </div>

        @php
        $b0 = $attendance->breaks[0] ?? null;
        $b1 = $attendance->breaks[1] ?? null;
        @endphp

        <div class="row">
            <div class="label">休憩</div>
            <div class="value time-range">
                <input type="time" name="breaks[0][start_at]"
                    value="{{ old('breaks.0.start_at', data_get($form ?? [], 'breaks.0.start_at', $dbB0S)) }}"
                    class="time-input" {{ $isLocked ? 'disabled' : '' }}>
                <span class="sep">〜</span>
                <input type="time" name="breaks[0][end_at]"
                    value="{{ old('breaks.0.end_at', data_get($form ?? [], 'breaks.0.end_at', $dbB0E)) }}"
                    class="time-input" {{ $isLocked ? 'disabled' : '' }}>
                @if ($errors->has('break_time'))
                    <p class="field-error">{{ $errors->first('break_time') }}</p>
                @endif
            </div>
        </div>

        <div class="row">
            <div class="label">休憩2</div>
            <div class="value time-range">
                <input type="time" name="breaks[1][start_at]"
                    value="{{ old('breaks.1.start_at', data_get($form ?? [], 'breaks.1.start_at', $dbB1S)) }}"
                    class="time-input" {{ $isLocked ? 'disabled' : '' }}>
                <span class="sep">〜</span>
                <input type="time" name="breaks[1][end_at]"
                    value="{{ old('breaks.1.end_at', data_get($form ?? [], 'breaks.1.end_at', $dbB1E)) }}"
                    class="time-input" {{ $isLocked ? 'disabled' : '' }}>
                @if ($errors->has('break_time'))
                    <p class="field-error">{{ $errors->first('break_time') }}</p>
                @endif
            </div>
        </div>

        <div class="row">
            <div class="label">備考</div>
            <div class="value">
            <textarea name="reason" rows="3" class="note-input"
                placeholder="備考を入力してください" {{ $isLocked ? 'disabled' : '' }}>{{ old('reason', data_get($form ?? [], 'reason', optional($attendance->request)->reason)) }}</textarea>
            @error('reason')
                <p class="field-error note-error">{{ $message }}</p>
            @enderror
            </div>
        </div>

        <div class="actions">
            @if (!$isLocked)
              <button type="submit" class="btn-primary">修正</button>
            @endif
        </div>
    </form>
    @if ($isLocked)
    <p class="pending-note">※ 承認待ちのため修正はできません。</p>
    @endif
    
    @if (session('status'))
    <p class="flash">{{ session('status') }}</p>
    @endif
    </div>
</main>
@endsection

