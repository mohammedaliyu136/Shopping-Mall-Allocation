@extends('layouts.public')
@section('title', 'Reset your password')
@section('wrapper', 'auth-wrap')

@section('content')
    {{-- SRS FR-002 - password reset. --}}
    <div class="card">
        <div class="card-head"><h2>Reset your password</h2></div>
        <div class="card-body">
            <p class="small muted">Enter the email address on your account and we will send you a reset link.</p>
            <form method="POST" action="{{ route('password.email') }}">
                @csrf
                <div class="field">
                    <label for="email">Email address</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>
                </div>
                <div class="actions">
                    <button type="submit" class="btn">Send reset link</button>
                    <a href="{{ route('login') }}" class="small">Back to sign in</a>
                </div>
            </form>
        </div>
    </div>
@endsection
