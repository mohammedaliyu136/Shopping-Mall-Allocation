@extends('layouts.public')
@section('title', 'Choose a new password')
@section('wrapper', 'auth-wrap')

@section('content')
    <div class="card">
        <div class="card-head"><h2>Choose a new password</h2></div>
        <div class="card-body">
            <form method="POST" action="{{ route('password.update') }}">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <div class="field">
                    <label for="email">Email address</label>
                    <input id="email" type="email" name="email" value="{{ old('email', $email) }}" required>
                </div>
                <div class="field">
                    <label for="password">New password</label>
                    <input id="password" type="password" name="password" required autocomplete="new-password">
                </div>
                <div class="field">
                    <label for="password_confirmation">Confirm new password</label>
                    <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">
                </div>
                <button type="submit" class="btn">Save new password</button>
            </form>
        </div>
    </div>
@endsection
