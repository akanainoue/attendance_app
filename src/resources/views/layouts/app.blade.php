<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>勤怠アプリ</title>
    <link rel="stylesheet" href="{{ asset('css/sanitize.css') }}" />
    <link rel="stylesheet" href="{{ asset('css/common.css') }}" />
    @yield('css')
</head>
<body>
<header class="header">
    <div class="header-logo">
        <a href="/" class="index-link">
        <img class="logo" src="{{ asset('logo/logo.svg') }}" alt="" style="width: 25% hight: 32px">
        </a>
    </div>
    @yield('nav')
</header>
<main>
    @yield('content')
</main>
</body>
</html>