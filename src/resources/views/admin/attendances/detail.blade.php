@extends('layouts.app')

@section('css')
<link rel="stylesheet" href="{{ asset('css/admin-attendance-detail.css') }}">
@endsection

@section('nav')
<nav class="nav">
  <a class="active" href="{{ url('/admin/attendance/list') }}">勤怠一覧</a>
  <a href="{{ url('/admin/staff/list') }}">スタッフ一覧</a>
  <a href="{{ url('/admin/stamp_correction_request/list') }}">申請一覧</a>
  <form method="POST" action="{{ url('/admin/logout') }}" class="logout-form">
    @csrf
    <button type="submit">ログアウト</button>
  </form>
</nav>
@endsection

@section('content')
<main class="main">
  <div class="container">

    <h1 class="page-title"><span class="bar"></span>勤怠詳細</h1>

    {{-- 更新フォーム（必要に応じて PATCH にしてください） --}}
    <form method="POST" action="{{ url('/admin/attendance/'.$attendance->id) }}" class="detail-form">
      @csrf
      @method('PATCH')

      <section class="card detail-grid">
        {{-- 名前 --}}
        <div class="row">
          <div class="th">名前</div>
          <div class="td td-span">{{ $attendance->user->name ?? '—' }}</div>
        </div>

        {{-- 日付（年 / 月日） --}}
        @php $d = \Illuminate\Support\Carbon::parse($attendance->work_date); @endphp
        <div class="row">
          <div class="th">日付</div>
          <div class="td">{{ $d->format('Y') }}年</div>
          <div class="tilde"></div>
          <div class="td">{{ $d->format('n月j日') }}</div>
        </div>

        {{-- 出勤・退勤 --}}
        <div class="row">
          <div class="th">出勤・退勤</div>
          <div class="td">
            <input name="clock_in_at"
                   type="time"
                   class="time-input"
                   value="{{ old('clock_in_at', data_get($form,'clock_in_at', $dbIn ?? optional($attendance->clock_in_at)->format('H:i'))) }}">
          </div>
          <div class="tilde">〜</div>
          <div class="td">
            <input name="clock_out_at"
                   type="time"
                   class="time-input"
                   value="{{old('clock_out_at', data_get($form, 'clock_out_at', $dbOut ?? optional($attendance->clock_out_at)->format('H:i'))) }}">
          </div>
          @if ($errors->has('work_time'))
                <p class="field-error">{{ $errors->first('work_time') }}</p>
            @endif
        </div>

        {{-- 休憩1 --}}
        @php
          $br1 = $attendance->breaks[0] ?? null;
          $br2 = $attendance->breaks[1] ?? null;
          $dbB0S = optional($br1?->start_at)->format('H:i');
          $dbB0E = optional($br1?->end_at)  ->format('H:i');
          $dbB1S = optional($br2?->start_at)->format('H:i');
          $dbB1E = optional($br2?->end_at)  ->format('H:i');
        @endphp
        <div class="row">
          <div class="th">休憩</div>
          <div class="td">
            <input name="breaks[0][start_at]" type="time" class="time-input"
                  value="{{ old('breaks.0.start_at', data_get($form, 'breaks.0.start_at', $dbB0S)) }}">
          </div>
          <div class="tilde">〜</div>
          <div class="td">
            <input name="breaks[0][end_at]" type="time" class="time-input"
                  value="{{ old('breaks.0.end_at', data_get($form, 'breaks.0.end_at', $dbB0E)) }}">
          </div>
          @if ($errors->has('break_time'))
                <p class="field-error">{{ $errors->first('break_time') }}</p>
          @endif
        </div>

        {{-- 休憩2 --}}
        <div class="row">
          <div class="th">休憩2</div>
          <div class="td">
            <input name="breaks[1][start_at]" type="time" class="time-input"
                  value="{{ old('breaks.1.start_at', data_get($form, 'breaks.1.start_at', $dbB1S)) }}">
          </div>
          <div class="tilde">〜</div>
          <div class="td">
            <input name="breaks[1][end_at]" type="time" class="time-input"
                  value="{{ old('breaks.1.end_at', data_get($form, 'breaks.1.end_at', $dbB1E)) }}">
          </div>
        </div>

        {{-- 備考 --}}
        <div class="row">
          <div class="th">備考</div>
          <div class="td td-span">
            <textarea name="note" class="note-input" rows="3"
              placeholder="">{{ old('reason', $form['reason'] ?? ($attendance->request->reason ?? '')) }}</textarea>
            @error('reason')
              <p class="field-error">{{ $message }}</p>
            @enderror  
          </div>
        </div>
      </section>

      <div class="actions">
        <button type="submit" class="btn-primary">修正</button>
      </div>
    </form>
  </div>
</main>
@endsection

