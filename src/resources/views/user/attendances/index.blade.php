@extends('layouts.app')

@section('css')
<link rel="stylesheet" href="{{ asset('css/attendance-list.css') }}">
@endsection

@section('nav')
<nav class="nav">
    <a href="{{ url('/attendance') }}">勤怠</a>
    <a class="active" href="{{ url('/attendance/list') }}">勤怠一覧</a>
    <a href="{{ url('/stamp_correction_request/list') }}">申請</a>
    <form method="POST" action="{{ url('/logout') }}" class="logout-form">
    @csrf
        <button type="submit">ログアウト</button>
    </form>
</nav>
@endsection

@section('content')
<main class="main">
    <div class="container">
        <h1 class="page-title">勤怠一覧</h1>

        {{-- 月ナビ --}}
        @php
          /** @var \Carbon\Carbon $month */
            $month   = $month ?? now()->startOfMonth();
            $prev    = $month->copy()->subMonth()->format('Y-m');
            $next    = $month->copy()->addMonth()->format('Y-m');
            $caption = $month->format('Y/m');
        @endphp

        <div class="month-nav card">
            <a class="prev" href="{{ url('/attendance/list?month='.$prev) }}">
                <span class="icon">←</span> 前月
            </a>
            <div class="current">
                <svg class="cal" viewBox="0 0 24 24" aria-hidden="true"><path d="M7 2v2H5a2 2 0 0 0-2 2v2h18V6a2 2 0 0 0-2-2h-2V2h-2v2H9V2H7zm14 8H3v10a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V10z"/></svg>
                <span>{{ $caption }}</span>
            </div>
            <a class="next" href="{{ url('/attendance/list?month='.$next) }}">
                翌月 <span class="icon">→</span>
            </a>
        </div>

        {{-- テーブル --}}
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
            @foreach ($rows as $row)
                {{-- $row: ['date'=>'06/01(木)','in'=>'09:00','out'=>'18:00','break'=>'1:00','total'=>'8:00','id'=>1] 想定 --}}
                <tr>
                    <td class="col-date">{{ $row['date'] }}</td>
                    <td>{{ $row['in'] ?? '-' }}</td>
                    <td>{{ $row['out'] ?? '-' }}</td>
                    <td>{{ $row['break'] ?? '0:00' }}</td>
                    <td>{{ $row['total'] ?? '-' }}</td>
                    <td>
                        <!-- @php
                        $link = $row['id']
                                ? url('/attendance/detail/'.$row['id'])
                                : url('/attendance/detail/date/'.$row['ymd']);
                        @endphp -->
                        <a class="link-detail" href="{{ url('/attendance/detail/'.$row['id']) }}">詳細</a>
                    </td>
                </tr>
            @endforeach
            </tbody>
            </table>
        </div>
    </div>
</main>
@endsection