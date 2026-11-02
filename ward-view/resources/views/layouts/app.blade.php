<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Ward View') · {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/ward.css') }}">
</head>
<body>
<header class="topbar">
    <a class="brand" href="{{ url('/') }}">{{ config('app.name') }}</a>
    @auth
        @php($currentWard = session('ward_id') ? \App\Models\Ward::find(session('ward_id')) : null)
        @if ($currentWard)
            <a href="{{ route('wards.show', $currentWard) }}">{{ $currentWard->name }}</a>
            <a href="{{ route('handover.index', $currentWard) }}">Handover &amp; tasks</a>
        @endif
        <a href="{{ route('wards.index') }}">Switch ward</a>
        @if (auth()->user()->isAdmin())
            <a href="{{ route('admin.wards.index') }}">Ward setup</a>
        @endif
        <span class="spacer"></span>
        <span class="who"><strong>{{ auth()->user()->name }}</strong><br>{{ auth()->user()->role->label() }}</span>
        <form method="POST" action="{{ route('logout') }}">@csrf <button class="link" type="submit">Sign out</button></form>
    @endauth
</header>
<main>
    @if (session('status'))
        <div class="flash ok" role="status">{{ session('status') }}</div>
    @endif
    @if (session('escalate'))
        <div class="flash warn" role="alert">⚠ {{ session('escalate') }}</div>
    @endif
    @if ($errors->any() && ! session('open_action'))
        <div class="flash err" role="alert">{{ $errors->first() }}</div>
    @endif
    @yield('content')
</main>
<dialog id="action-dialog" aria-label="Action form"></dialog>
@stack('dialogs')
<script src="{{ asset('js/ward.js') }}" defer></script>
</body>
</html>
