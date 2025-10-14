@extends('layouts.app')

@section('css')
<link rel="stylesheet" href="{{ asset('css/register.css') }}" />
@endsection

@section('content')
<div class="register-form">
    <h2 class="form-title">会員登録</h2>
    <form class="form-content" action="/register" method="post">
        @csrf
        <div class="form__group">
            <label class="form__label" for="name">ユーザー名</label>
            <input class="form__input" type="text" name="name" id="name" value="{{ old('name') }}">
            <p class="form__error-message">
            @error('name')
            {{ $message }}
            @enderror
            </p>
        </div>
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
        <div class="form__group">
            <label class="form__label" for="password-repeat">確認用パスワード</label>
            <input class="form__input" type="password" name="password_confirmation" id="password-repeat">
            <p class="form__error-message">
            @error('password_confirmation')
            {{ $message }}
            @enderror
            </p>
        </div>
        <input class="form__btn" type="submit" value="登録する">
        <a href="/login" class="login-link">ログインはこちら</a>
    </form>
</div>
@endsection