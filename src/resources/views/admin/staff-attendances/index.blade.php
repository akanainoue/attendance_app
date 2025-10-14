@extends('layouts.app')

@section('css')
<link rel="stylesheet" href="{{ asset('css/admin-staff-attendance.css') }}">
@endsection

@section('nav')
<nav class="nav">
  <a href="{{ url('/admin/attendance/list') }}">勤怠一覧</a>
  <a class="active" href="{{ url('/admin/staff/list') }}">スタッフ一覧</a>
  <a href="{{ url('/admin/stamp_correction_request/list') }}">申請一覧</a>
  <form method="POST" action="{{ url('/admin/logout') }}" class="logout-form">
    @csrf
    <button type="submit">ログアウト</button>
  </form>
</nav>
@endsection

@section('content')
<main class="main">
  <div class="container page-wrap">

    <h1 class="page-title">{{ $user->name }}さんの勤怠</h1>

    @php
      $prev = $month->copy()->subMonth()->format('Y-m');
      $next = $month->copy()->addMonth()->format('Y-m');
      $caption = $month->format('Y/m');
    @endphp

    {{-- 月ナビ --}}
    <div class="month-nav card">
      <a class="prev" href="{{ url('/admin/attendance/staff/'.$user->id.'?month='.$prev) }}">
        <span class="icon">←</span> 前月
      </a>
      <div class="current">
        <svg class="cal" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M7 2v2H5a2 2 0 0 0-2 2v2h18V6a2 2 0 0 0-2-2h-2V2h-2v2H9V2H7zm14 8H3v10a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V10z"/>
        </svg>
        <span>{{ $caption }}</span>
      </div>
      <a class="next" href="{{ url('/admin/attendance/staff/'.$user->id.'?month='.$next) }}">
        翌月 <span class="icon">→</span>
      </a>
    </div>

    {{-- 表 --}}
    <div class="sheet card">
      <table class="att-table">
        <thead>
          <tr>
            <th>日付</th>
            <th>出勤</th>
            <th>退勤</th>
            <th>休憩</th>
            <th>合計</th>
            <th>詳細</th>
          </tr>
        </thead>
        <tbody>
        @foreach ($rows as $r)
          <tr>
            <td class="col-date">{{ $r['date'] }}</td>
            <td>{{ $r['in'] }}</td>
            <td>{{ $r['out'] }}</td>
            <td>{{ $r['break'] }}</td>
            <td>{{ $r['total'] }}</td>
            <td class="col-detail">
              @if($r['id'])
                <a class="link-detail" href="{{ url('/admin/attendance/'.$r['id']) }}">詳細</a>
              @else
                <span class="link-detail is-disabled">詳細</span>
              @endif
            </td>
          </tr>
        @endforeach
        </tbody>
      </table>
    </div>

    {{-- 右下：CSV出力 --}}
    <div class="export-area">
      <form method="GET" action="{{ url('/admin/attendance/staff/'.$user->id.'/csv') }}">
        <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
        <button type="submit" class="btn-export">CSV出力</button>
      </form>
    </div>

  </div>
</main>
@endsection

