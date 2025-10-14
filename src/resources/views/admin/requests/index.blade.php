@extends('layouts.app')

@section('css')
<link rel="stylesheet" href="{{ asset('css/admin-request-list.css') }}">
@endsection

@section('nav')
<nav class="nav">
    <a href="{{ url('/admin/attendance/list') }}">勤怠一覧</a>
    <a href="{{ url('/admin/staff/list') }}">スタッフ一覧</a>
    <a class="active" href="{{ url('/admin/stamp_correction_request/list') }}">申請一覧</a>
    <form method="POST" action="{{ url('/admin/logout') }}" class="logout-form">
        @csrf
        <button type="submit">ログアウト</button>
    </form>
</nav>
@endsection

@section('content')
<main class="main">
    <div class="container">
        <h1 class="page-title">申請一覧</h1>

        {{-- タブ（承認待ち / 承認済み） --}}
        <div class="tabs">
          {{-- クエリ ?status=pending / approved を想定 --}}
          <a href="{{ url('/admin/stamp_correction_request/list?status=pending') }}"
             class="tab {{ ($status ?? 'pending') === 'pending' ? 'active' : '' }}">承認待ち</a>
          <a href="{{ url('/admin/stamp_correction_request/list?status=approved') }}"
             class="tab {{ ($status ?? 'pending') === 'approved' ? 'active' : '' }}">承認済み</a>
        </div>

        <div class="sheet card">
          <table class="req-table">
            <thead>
              <tr>
                <th class="col-status">状態</th>
                <th class="col-name">名前</th>
                <th class="col-target">対象日時</th>
                <th class="col-reason">申請理由</th>
                <th class="col-applied">申請日時</th>
                <th class="col-detail">詳細</th>
              </tr>
            </thead>
            <tbody>
            @forelse($rows as $r)
              {{-- $r 例：
                   ['status'=>'承認待ち','name'=>'西 侑奈','target'=>'2023/06/01',
                    'reason'=>'遅延のため','applied'=>'2023/06/02','id'=>123] --}}
              <tr>
                <td class="col-status">{{ $r['status'] }}</td>
                <td class="col-name">{{ $r['name'] }}</td>
                <td class="col-target">{{ $r['target'] }}</td>
                <td class="col-reason">{{ $r['reason'] ?? '-' }}</td>
                <td class="col-applied">{{ $r['applied'] }}</td>
                <td class="col-detail">
                  <a class="link-detail" href="{{ url('/admin/stamp_correction_request/approve/'.$r['id']) }}">詳細</a>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="6" class="empty">表示する申請はありません</td>
              </tr>
            @endforelse
            </tbody>
          </table>
        </div>
    </div>
</main>
@endsection
