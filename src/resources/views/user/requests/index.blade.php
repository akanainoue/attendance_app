@extends('layouts.app')

@section('css')
<link rel="stylesheet" href="{{ asset('css/request-list.css') }}">
@endsection

@section('nav')
<nav class="nav">
    <a href="{{ url('/attendance') }}">勤怠</a>
    <a href="{{ url('/attendance/list') }}">勤怠一覧</a>
    <a class="active" href="{{ url('/stamp_correction_request/list') }}">申請</a>
    <form method="POST" action="{{ url('/logout') }}" class="logout-form">
        @csrf
        <button type="submit">ログアウト</button>
    </form>
</nav>
@endsection

@section('content')
<main class="req-main">
    <div class="container">
        <h1 class="page-title">申請一覧</h1>

        {{-- タブ（承認待ち / 承認済み） --}}
        @php $tab = $tab ?? 'pending'; @endphp
        <div class="tabs">
            <a href="{{ url('/stamp_correction_request/list?tab=pending') }}"
                class="tab {{ $tab === 'pending' ? 'is-active' : '' }}">承認待ち</a>
            <a href="{{ url('/stamp_correction_request/list?tab=approved') }}"
                class="tab {{ $tab === 'approved' ? 'is-active' : '' }}">承認済み</a>
        </div>
        <div class="tabs-divider"></div>

        {{-- テーブル --}}
        <div class="sheet card">
            <table class="req-table">
            <thead>
                <tr>
                    <th>状態</th>
                    <th>名前</th>
                    <th>対象日時</th>
                    <th>申請理由</th>
                    <th>申請日時</th>
                    <th>詳細</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                {{-- $row 例:
                ['status'=>'承認待ち','name'=>'西 伶奈','target'=>'2023/06/01',
                'reason'=>'遅延のため','applied'=>'2023/06/02','id'=>1] --}}
                <tr>
                    <td><span class="badge">{{ $row['status'] }}</span></td>
                    <td>{{ $row['name'] }}</td>
                    <td class="mono">{{ $row['target'] }}</td>
                    <td class="ellipsis" title="{{ $row['reason'] }}">{{ $row['reason'] }}</td>
                    <td class="mono">{{ $row['applied'] }}</td>
                    <td class="col-detail">
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
