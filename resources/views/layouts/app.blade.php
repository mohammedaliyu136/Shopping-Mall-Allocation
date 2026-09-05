<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') &middot; {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
<header class="topbar">
    <div class="brand">
        {{ config('app.name') }}
        <small>Allocation, Billing &amp; Owner Management</small>
    </div>
    <div class="spacer"></div>
    @auth
        <div class="who">
            <strong>{{ auth()->user()->name }}</strong>
            {{ auth()->user()->role?->name ?? 'No role' }}
        </div>
        <a href="{{ route('password.change') }}" class="small">Change password</a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn secondary sm">Sign out</button>
        </form>
    @endauth
</header>

<div class="shell">
    @includeWhen(! auth()->user()?->isShopOwner(), 'partials.admin-nav')
    @includeWhen(auth()->user()?->isShopOwner(), 'partials.portal-nav')

    <main class="content">
        @include('partials.flash')
        @yield('content')
    </main>
</div>
</body>
</html>
