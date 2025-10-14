@extends('layouts.app')

@section('css')
<link rel="stylesheet" href="{{ asset('css/admin-staff-list.css') }}">
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
  <div class="container">

    <h1 class="page-title"><span class="bar"></span>スタッフ一覧</h1>

    <div class="sheet card">
      <table class="staff-table">
        <thead>
          <tr>
            <th>名前</th>
            <th>メールアドレス</th>
            <th class="col-action">月次勤怠</th>
          </tr>
        </thead>
        <tbody>
        @foreach($staffs as $user)
          <tr>
            <td class="name">{{ $user->name }}</td>
            <td class="email">{{ $user->email }}</td>
            <td class="col-action">
              <a class="link-detail" href="{{ url('/admin/attendance/staff/'.$user->id) }}">詳細</a>
            </td>
          </tr>
        @endforeach
        </tbody>
      </table>
    </div>

  </div>
</main>
@endsection
