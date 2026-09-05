@extends('layouts.app')
@section('title', 'Users')

@section('content')
    <div class="page-head">
        <div><h1>User accounts</h1><p class="sub">Staff and owner portal accounts (SRS 5).</p></div>
        <div class="spacer"></div>
        @can(\App\Support\Permissions::USERS_MANAGE)
            <a href="{{ route('admin.users.create') }}" class="btn">New user</a>
        @endcan
    </div>

    <div class="card"><div class="card-body">
        <form method="GET" class="filters">
            <div class="field"><label>Search</label><input type="search" name="q" value="{{ request('q') }}" placeholder="Name or email"></div>
            <div class="field"><label>Role</label>
                <select name="role">
                    <option value="">All</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role->slug }}" @selected(request('role') === $role->slug)>{{ $role->name }}</option>
                    @endforeach
                </select></div>
            <button type="submit" class="btn secondary">Filter</button>
            <a href="{{ route('admin.users.index') }}" class="btn secondary">Reset</a>
        </form>
    </div></div>

    <div class="card"><div class="card-body tight table-wrap">
        <table class="data">
            <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Linked owner</th><th>Status</th><th>Last activity</th><th></th></tr></thead>
            <tbody>
            @forelse ($users as $user)
                <tr>
                    <td>{{ $user->name }}</td>
                    <td>{{ $user->email }}</td>
                    <td>{{ $user->role?->name ?? '—' }}</td>
                    <td>{{ $user->owner?->full_name ?? '—' }}</td>
                    <td>@include('partials.status-badge', ['status' => $user->is_active ? 'active' : 'suspended'])</td>
                    <td class="nowrap small">{{ $user->last_activity_at?->diffForHumans() ?? 'Never' }}</td>
                    <td class="right">
                        @can(\App\Support\Permissions::USERS_MANAGE)
                            <a href="{{ route('admin.users.edit', $user) }}" class="btn sm secondary">Edit</a>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty">No users match these filters.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div></div>
    {{ $users->links() }}
@endsection
