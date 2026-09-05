@extends('layouts.app')
@section('title', 'Change password')

@section('content')
    {{-- SRS FR-002 - password change. --}}
    <div class="page-head"><h1>Change your password</h1></div>

    <div class="card" style="max-width:520px">
        <div class="card-body">
            <form method="POST" action="{{ route('password.change.update') }}">
                @csrf
                @method('PUT')
                <div class="field">
                    <label for="current_password">Current password</label>
                    <input id="current_password" type="password" name="current_password" required autocomplete="current-password">
                </div>
                <div class="field">
                    <label for="password">New password</label>
                    <input id="password" type="password" name="password" required autocomplete="new-password">
                    <div class="hint">At least 8 characters. Passwords are stored hashed, never in plain text.</div>
                </div>
                <div class="field">
                    <label for="password_confirmation">Confirm new password</label>
                    <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">
                </div>
                <button type="submit" class="btn">Update password</button>
            </form>
        </div>
    </div>
@endsection
