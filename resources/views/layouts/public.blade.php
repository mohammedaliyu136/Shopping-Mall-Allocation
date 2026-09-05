<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Verify') &middot; {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
<header class="topbar">
    <div class="brand">
        {{ config('app.name') }}
        <small>Document Verification</small>
    </div>
    <div class="spacer"></div>
    @auth
        <a href="{{ auth()->user()->isShopOwner() ? route('portal.dashboard') : route('admin.dashboard') }}">My dashboard</a>
    @else
        <a href="{{ route('login') }}">Staff &amp; owner sign in</a>
    @endauth
</header>

<main class="@yield('wrapper', 'public-wrap')">
    @include('partials.flash')
    @yield('content')
</main>
</body>
</html>
