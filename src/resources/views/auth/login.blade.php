@extends('layouts.app')

@section('css')
<link rel="stylesheet" href="{{ asset('css/login.css') }}" />
@endsection

@section('content')
<div class="login-form">
    <h2 class="form-title">ログイン</h2>
    <form class="form-content" action="/login" method="post">
        @csrf
        <div class="form__group">
            <label class="form__label" for="email">メールアドレス</label>
            <input class="form__input" type="mail" name="email" id="email" value="{{ old('email') }}">
            <p class="form__error-message">
            @error('email')
            {{ $message }}
            @enderror
            </p>
        </div>
        <div class="form__group">
            <label class="form__label" for="password">パスワード</label>
            <input class="form__input" type="password" name="password" id="password">
            <p class="form__error-message">
            @error('password')
            {{ $message }}
            @enderror
            </p>
        </div>
        <input class="form__btn" type="submit" value="ログインする">
        <a href="/register" class="register-link">会員登録はこちら</a>
    </form>
</div>
@endsection('content')