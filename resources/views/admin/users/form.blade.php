@extends('layouts.app')
@section('title', $user->exists ? 'Edit user' : 'New user')

@section('content')
    <div class="page-head"><h1>{{ $user->exists ? 'Edit '.$user->name : 'New user account' }}</h1></div>

    <div class="card" style="max-width:620px"><div class="card-body">
        <form method="POST" action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}">
            @csrf
            @if ($user->exists) @method('PUT') @endif

            <div class="grid cols-2">
                <div class="field"><label for="name">Full name</label>
                    <input id="name" type="text" name="name" value="{{ old('name', $user->name) }}" required></div>
                <div class="field"><label for="email">Email address</label>
                    <input id="email" type="email" name="email" value="{{ old('email', $user->email) }}" required></div>
                <div class="field"><label for="phone">Phone</label>
                    <input id="phone" type="tel" name="phone" value="{{ old('phone', $user->phone) }}"></div>
                <div class="field"><label for="role_id">Role</label>
                    <select id="role_id" name="role_id" required>
                        @foreach ($roles as $role)
                            <option value="{{ $role->id }}" @selected(old('role_id', $user->role_id) == $role->id)>{{ $role->name }}</option>
                        @endforeach
                    </select></div>
            </div>

            <div class="field"><label for="owner_id">Linked owner record</label>
                <select id="owner_id" name="owner_id">
                    <option value="">—</option>
                    @foreach ($owners as $owner)
                        <option value="{{ $owner->id }}" @selected(old('owner_id', $user->owner_id) == $owner->id)>{{ $owner->full_name }} ({{ $owner->owner_code }})</option>
                    @endforeach
                </select>
                <div class="hint">Required for shop-owner accounts so the portal can find their records.</div></div>

            <div class="grid cols-2">
                <div class="field"><label for="password">{{ $user->exists ? 'New password' : 'Password' }}</label>
                    <input id="password" type="password" name="password" autocomplete="new-password" @required(! $user->exists)>
                    @if ($user->exists)<div class="hint">Leave blank to keep the current password.</div>@endif</div>
                <div class="field"><label for="password_confirmation">Confirm password</label>
                    <input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" @required(! $user->exists)></div>
            </div>

            <div class="field"><label><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active ?? true))>
                    Account is active</label></div>

            <div class="actions">
                <button type="submit" class="btn">{{ $user->exists ? 'Save changes' : 'Create account' }}</button>
                <a href="{{ route('admin.users.index') }}" class="btn secondary">Cancel</a>
            </div>
        </form>
    </div></div>
@endsection
