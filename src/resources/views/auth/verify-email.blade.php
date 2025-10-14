@extends('layouts.app')

@section('css')
<link rel="stylesheet" href="{{ asset('css/verify-email.css') }}">
@endsection

@section('content')
<div class="verify-container">
    <p class="verify-message">
        登録していただいたメールアドレスに認証メールを送付しました。<br>
        メール認証を完了してください。
    </p>

    <a class="verify-button" href="http://localhost:8025" target="_blank">認証はこちらから</a>

    <form method="POST" action="{{ route('verification.send') }}">
        @csrf
        <button class="resend-link" type="submit">認証メールを再送する</button>
    </form>
</div>
@endsection

<!-- ルートは Fortify が用意（verification.send）。
もし名前付きルートが不安なら、URL 直指定でもOK:
action="{{ url('/email/verification-notification') }}" -->