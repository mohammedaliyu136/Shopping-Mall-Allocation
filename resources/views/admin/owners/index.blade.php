@extends('layouts.app')
@section('title', 'Owners')

@section('content')
    <div class="page-head">
        <div><h1>Shop owners</h1><p class="sub">Registered owners and their allocations (FR-040, FR-043).</p></div>
        <div class="spacer"></div>
        @can(\App\Support\Permissions::OWNERS_CREATE)
            <a href="{{ route('admin.owners.create') }}" class="btn">Register owner</a>
        @endcan
    </div>

    <div class="card"><div class="card-body">
        {{-- FR-043 - search by name, phone, email, shop number or allocation reference. --}}
        <form method="GET" class="filters">
            <div class="field" style="min-width:280px"><label>Search</label>
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Name, phone, email, shop number or allocation reference">
            </div>
            <div class="field"><label>Status</label>
                <select name="status">
                    <option value="">All</option>
                    @foreach (['active', 'inactive', 'suspended'] as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn secondary">Search</button>
            <a href="{{ route('admin.owners.index') }}" class="btn secondary">Reset</a>
        </form>
    </div></div>

    <div class="card"><div class="card-body tight table-wrap">
        <table class="data">
            <thead><tr><th>Code</th><th>Name</th><th>Business</th><th>Phone</th><th>Email</th><th class="num">Allocations</th><th>Status</th></tr></thead>
            <tbody>
            @forelse ($owners as $owner)
                <tr>
                    <td class="mono">{{ $owner->owner_code }}</td>
                    <td><a href="{{ route('admin.owners.show', $owner) }}">{{ $owner->full_name }}</a></td>
                    <td>{{ $owner->business_name ?: '—' }}</td>
                    <td class="nowrap">{{ $owner->phone }}</td>
                    <td>{{ $owner->email ?: '—' }}</td>
                    <td class="num">{{ $owner->allocations_count }}</td>
                    <td>@include('partials.status-badge', ['status' => $owner->status])</td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty">No owners match this search.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div></div>
    {{ $owners->links() }}
@endsection
