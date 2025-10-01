@extends('layouts.app')

@section('css')
<link rel="stylesheet" href="{{ asset('css/admin-request-review.css') }}">
@endsection

@section('nav')
<nav class="nav">
  <a href="{{ url('/admin/attendance/list') }}">勤怠一覧</a>
  <a href="{{ url('/admin/staff/list') }}">スタッフ一覧</a>
  <a class="active" href="{{ url('/admin/stamp_correction_request/list') }}">申請一覧</a>
  <form action="{{ url('/admin/logout') }}" method="POST" class="logout-form">
    @csrf
    <button type="submit">ログアウト</button>
  </form>
</nav>
@endsection

@section('content')
<main class="main review-main">
  <div class="review-container">
    <h1 class="page-title">勤怠詳細</h1>

    <section class="review-card">
      {{-- 名前 --}}
      <div class="row">
        <div class="th">名前</div>
        <div class="td td-center">
          <span class="text-strong">{{ $name }}</span>
        </div>
      </div>

      {{-- 日付 --}}
      <div class="row">
        <div class="th">日付</div>
        <div class="td td-date">
          <div class="date-split">
            <span class="y">{{ $dateYear }}年</span>
            <span class="md">{{ $dateMd }}</span>
          </div>
        </div>
      </div>

      {{-- 出勤・退勤 --}}
      <div class="row">
        <div class="th">出勤・退勤</div>
        <div class="td td-pair">
          <span class="time-box">{{ $in }}</span>
          <span class="wave">〜</span>
          <span class="time-box">{{ $out }}</span>
        </div>
      </div>

      {{-- 休憩１ --}}
      <div class="row">
        <div class="th">休憩</div>
        <div class="td td-pair">
          <span class="time-box">{{ $b1s }}</span>
          <span class="wave">〜</span>
          <span class="time-box">{{ $b1e }}</span>
        </div>
      </div>

      {{-- 休憩２ --}}
      <div class="row">
        <div class="th">休憩2</div>
        <div class="td td-pair">
          <span class="time-box">{{ $b2s }}</span>
          <span class="wave">〜</span>
          <span class="time-box">{{ $b2e }}</span>
        </div>
      </div>

      {{-- 備考（申請理由） --}}
      <div class="row row-note">
        <div class="th">備考</div>
        <div class="td">
          <div class="note-box">{{ $note }}</div>
        </div>
      </div>
    </section>

    {{-- アクション --}}
    <div class="actions">
      @if($approved ?? false)
        <button class="btn btn-ghost" disabled>承認済み</button>
      @else
        <form action="{{ url('/admin/requests/'.$requestId.'/accept') }}" method="POST">
          @csrf
          @method('PATCH')
          <button type="submit" class="btn">承認</button>
        </form>
      @endif
    </div>
  </div>
</main>
@endsection




