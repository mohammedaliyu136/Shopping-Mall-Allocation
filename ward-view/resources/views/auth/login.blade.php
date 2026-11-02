@extends('layouts.app')
@section('title', 'Sign in')
@section('content')
<div class="login">
    <div class="panel">
        <h1>Sign in</h1>
        <p class="muted">Configurable ward view</p>
        <form method="POST" action="{{ url('/login') }}">
            @csrf
            <label for="email">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>
            <label for="password">Password</label>
            <input id="password" type="password" name="password" required>
            <label class="inline"><input type="checkbox" name="remember" value="1"> Keep me signed in</label>
            <p><button class="btn" type="submit">Sign in</button></p>
        </form>
    </div>
    @if (app()->environment('local'))
        <div class="panel">
            <h3>Demo accounts</h3>
            <p class="muted">Password: <code>password</code></p>
            <table class="demo">
                <tr><th>Email</th><th>Role</th><th>Ward</th></tr>
                <tr><td>admin@hospital.test</td><td>Admin</td><td>—</td></tr>
                <tr><td>dr.okafor@hospital.test</td><td>Doctor</td><td>Medical Ward A</td></tr>
                <tr><td>nurse.bello@hospital.test</td><td>Nurse</td><td>Medical Ward A</td></tr>
                <tr><td>dr.adeyemi@hospital.test</td><td>Doctor</td><td>ICU</td></tr>
                <tr><td>nurse.yusuf@hospital.test</td><td>Nurse</td><td>ICU</td></tr>
                <tr><td>dr.eze@hospital.test</td><td>Doctor</td><td>A&amp;E</td></tr>
                <tr><td>nurse.danjuma@hospital.test</td><td>Nurse</td><td>A&amp;E</td></tr>
            </table>
        </div>
    @endif
</div>
@endsection
