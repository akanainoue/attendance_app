@extends('layouts.app')

@section('css')
<link rel="stylesheet" href="{{ asset('css/admin-attendance-list.css') }}">
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

        {{-- タイトル --}}
        <h1 class="page-title">
            <span class="bar"></span>{{ $date->format('Y年n月j日') }}の勤怠
        </h1>

        {{-- 日付ナビ（前日 / 当日 / 翌日） --}}
        @php
            $prev = $date->copy()->subDay()->toDateString();
            $next = $date->copy()->addDay()->toDateString();
        @endphp
        <div class="day-nav card">
            <a class="prev" href="{{ url('/admin/attendance/list?date='.$prev) }}">
            <span class="arrow">←</span> 前日
            </a>

            <div class="current">
            <svg class="cal" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M7 2v2H5a2 2 0 0 0-2 2v2h18V6a2 2 0 0 0-2-2h-2V2h-2v2H9V2H7zm14 8H3v10a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V10z"/>
            </svg>
            <span class="date">{{ $date->format('Y/m/d') }}</span>
            </div>

            <a class="next" href="{{ url('/admin/attendance/list?date='.$next) }}">
            翌日 <span class="arrow">→</span>
            </a>
        </div>

        {{-- 一覧テーブル --}}
        <div class="sheet card">
            <table class="att-table">
            <thead>
                <tr>
                    <th class="col-name">名前</th>
                    <th>出勤</th>
                    <th>退勤</th>
                    <th>休憩</th>
                    <th>合計</th>
                    <th class="col-detail">詳細</th>
                </tr>
            </thead>
            <tbody>
            @foreach ($rows as $row)
                
                <tr>
                    <td class="col-name">
                        {{ $row['name'] }}
                        @if ($row['is_pending'])
                          <span class="badge badge-pending">申請中</span>
                        @endif
                    </td>
                    <td>{{ $row['in'] }}</td>
                    <td>{{ $row['out'] }}</td>
                    <td>{{ $row['break'] }}</td>
                    <td>{{ $row['total'] }}</td>
                    <td class="col-detail">
                        <a class="link-detail" href="{{ url('/admin/attendance/'.$row['attendance_id']) }}">詳細</a>
                    </td>
                </tr>
            @endforeach
            
            </tbody>
            </table>
        </div>
    </div>
</main>
@endsection

