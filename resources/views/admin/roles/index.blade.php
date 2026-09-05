@extends('layouts.app')
@section('title', 'Roles & permissions')

@section('content')
    <div class="page-head">
        <div><h1>Roles &amp; permissions</h1>
            <p class="sub">The permissions matrix that governs every feature (FR-010, FR-011).</p></div>
        <div class="spacer"></div>
        <a href="{{ route('admin.roles.create') }}" class="btn">New role</a>
    </div>

    <div class="card"><div class="card-body tight table-wrap">
        <table class="data">
            <thead><tr><th>Permission</th>
                @foreach ($roles as $role)<th class="num">{{ $role->name }}</th>@endforeach
            </tr></thead>
            <tbody>
            @foreach (collect($catalogue)->groupBy(fn ($meta) => $meta[1]) as $group => $items)
                <tr><th colspan="{{ $roles->count() + 1 }}">{{ $group }}</th></tr>
                @foreach ($items as $slug => $meta)
                    <tr>
                        <td>{{ $meta[0] }} <span class="mono muted small">{{ $slug }}</span></td>
                        @foreach ($roles as $role)
                            <td class="num">
                                @if ($role->slug === \App\Models\Role::SUPER_ADMIN || $role->permissions->contains('slug', $slug))
                                    <span class="badge ok">&check;</span>
                                @else
                                    <span class="muted">&ndash;</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            @endforeach
            <tr>
                <th>Users holding this role</th>
                @foreach ($roles as $role)
                    <td class="num"><a href="{{ route('admin.roles.edit', $role) }}">{{ $role->users_count }} &middot; edit</a></td>
                @endforeach
            </tr>
            </tbody>
        </table>
    </div></div>
@endsection
