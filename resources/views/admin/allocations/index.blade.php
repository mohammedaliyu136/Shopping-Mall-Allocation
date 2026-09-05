@extends('layouts.app')
@section('title', 'Allocations')

@section('content')
    <div class="page-head">
        <div><h1>Allocations</h1><p class="sub">Every allocation ever raised is retained as a permanent record (FR-123).</p></div>
        <div class="spacer"></div>
        @can(\App\Support\Permissions::ALLOCATIONS_CREATE)
            <a href="{{ route('admin.allocations.create') }}" class="btn">New allocation</a>
        @endcan
    </div>

    <div class="card"><div class="card-body">
        <form method="GET" class="filters">
            <div class="field" style="min-width:260px"><label>Search</label>
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Reference, owner or shop number"></div>
            <div class="field"><label>Status</label>
                <select name="status">
                    <option value="">All</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ str($status)->replace('_',' ')->title() }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn secondary">Filter</button>
            <a href="{{ route('admin.allocations.index') }}" class="btn secondary">Reset</a>
        </form>
    </div></div>

    <div class="card"><div class="card-body tight table-wrap">
        <table class="data">
            <thead><tr><th>Reference</th><th>Shop</th><th>Owner</th><th>Date</th><th>Type</th><th>Status</th></tr></thead>
            <tbody>
            @forelse ($allocations as $allocation)
                <tr>
                    <td><a href="{{ route('admin.allocations.show', $allocation) }}" class="mono">{{ $allocation->reference_number }}</a></td>
                    <td>{{ $allocation->shop?->label() }}</td>
                    <td>{{ $allocation->owner?->full_name }}</td>
                    <td class="nowrap">{{ $allocation->allocation_date?->toFormattedDateString() }}</td>
                    <td>{{ str($allocation->allocation_type)->replace('_',' ')->title() }}</td>
                    <td>@include('partials.status-badge', ['status' => $allocation->status])</td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">No allocations match these filters.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div></div>
    {{ $allocations->links() }}
@endsection
