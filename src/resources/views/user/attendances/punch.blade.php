@extends('layouts.app')

@section('css')
<link rel="stylesheet" href="{{ asset('css/punch.css') }}" />
@endsection

@php
  // $status は 'working' | 'breaking' | 'done' | 'off' を想定
  $status = $status ?? 'off';
  $chipText = [
    'off'      => '勤務外',
    'working'  => '出勤中',
    'breaking' => '休憩中',
    'done'     => '退勤済'
  ][$status] ?? '勤務外';

  // サーバーで描画（JSなし）
  $date = $date ?? now()->format('Y年n月j日(D)');
  $time = $time ?? now()->format('H:i');
@endphp

@section('nav')
<nav class="nav">
  <a href="{{ url('/attendance') }}">勤怠</a>
  {{-- 退勤済みだけラベルを「今月の出勤一覧」に変更（リンク先は同じでOK） --}}
  <a href="{{ url('/attendance/list') }}">
    {{ $status === 'done' ? '今月の出勤一覧' : '勤怠一覧' }}
  </a>
  <a href="{{ url('/stamp_correction_request/list') }}">申請</a>
  <form method="POST" action="{{ url('/logout') }}" class="logout-form">
    @csrf
    <button type="submit">ログアウト</button>
  </form>
</nav>
@endsection

@section('content')
<main class="main">
  <div class="container center-stack">
    <span class="chip">{{ $chipText }}</span>
    <h1 class="page-date">{{ $date }}</h1>
    <div class="big-clock">{{ $time }}</div>
    @if (session('status'))
      <div class="flash">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
      <ul class="errors">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    @endif
    {{-- ボタン群：状態ごとに切り替え --}}
    <div class="actions">
      @if ($status === 'off')
        <form method="POST" action="{{ url('/attendance/clock-in') }}">@csrf
          <button type="submit" class="btn-primary">出 勤</button>
        </form>
      @elseif ($status === 'working')
        <div class="row-buttons">
          <form method="POST" action="{{ url('/attendance/clock-out') }}">@csrf
            <button type="submit" class="btn-primary">退 勤</button>
          </form>
          <form method="POST" action="{{ url('/attendance/break-start') }}">@csrf
            <button type="submit" class="btn-ghost">休憩入</button>
          </form>
        </div>
      @elseif ($status === 'breaking')
        <form method="POST" action="{{ url('/attendance/break-end') }}">@csrf
          <button type="submit" class="btn-ghost">休憩戻</button>
        </form>
      @elseif ($status === 'done')
        <p class="done-message">お疲れ様でした。</p>
      @endif
    </div>
  </div>
</main>
@endsection
