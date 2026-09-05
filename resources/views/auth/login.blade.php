@extends('layouts.public')
@section('title', 'Sign in')
@section('wrapper', 'auth-wrap')

@section('content')
    {{-- SRS FR-001 - user login. --}}
    <div class="card">
        <div class="card-head"><h2>Sign in</h2></div>
        <div class="card-body">
            <form method="POST" action="{{ route('login') }}">
                @csrf
                <div class="field">
                    <label for="email">Email address</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
                </div>
                <div class="field">
                    <label for="password">Password</label>
                    <input id="password" type="password" name="password" required autocomplete="current-password">
                </div>
                <div class="field">
                    <label><input type="checkbox" name="remember" value="1"> Keep me signed in</label>
                </div>
                <div class="actions">
                    <button type="submit" class="btn">Sign in</button>
                    <a href="{{ route('password.request') }}" class="small">Forgotten your password?</a>
                </div>
            </form>
        </div>
    </div>
    <p class="small muted">Staff use the administration portal; registered shop owners are taken to their self-service portal.</p>
@endsection
