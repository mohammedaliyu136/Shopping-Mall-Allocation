@extends('layouts.app')
@section('title', 'Allocation report')

@section('content')
    <div class="page-head">
        <div><h1>Allocation report</h1><p class="sub">FR-160</p></div>
        <div class="spacer"></div>
        <a href="{{ route('admin.reports.export', ['report' => 'allocations', 'from' => request('from'), 'to' => request('to')]) }}" class="btn secondary">Export CSV</a>
    </div>

    <div class="grid cols-4">
        <div class="stat"><div class="label">Total shops</div><div class="value">{{ $report['total_shops'] }}</div></div>
        <div class="stat"><div class="label">Available</div><div class="value">{{ $report['available_shops'] }}</div></div>
        <div class="stat"><div class="label">Allocated</div><div class="value">{{ $report['allocated_shops'] }}</div></div>
        <div class="stat"><div class="label">Unallocated</div><div class="value">{{ $report['unallocated_shops'] }}</div></div>
    </div>

    <div class="grid cols-2 mt">
        <div class="card"><div class="card-head"><h2>Shops by status</h2></div><div class="card-body tight table-wrap">
            <table class="data"><tbody>
            @foreach ($report['shops_by_status'] as $status => $count)
                <tr><td>@include('partials.status-badge', ['status' => $status])</td><td class="num">{{ $count }}</td></tr>
            @endforeach
            </tbody></table>
        </div></div>
        <div class="card"><div class="card-head"><h2>Allocations by status</h2></div><div class="card-body tight table-wrap">
            <table class="data"><tbody>
            @forelse ($report['allocations_by_status'] as $status => $count)
                <tr><td>@include('partials.status-badge', ['status' => $status])</td><td class="num">{{ $count }}</td></tr>
            @empty
                <tr><td class="empty">No allocations recorded.</td></tr>
            @endforelse
            </tbody></table>
        </div></div>
    </div>

    <div class="card">
        <div class="card-head"><h2>Allocation history</h2>
            <div class="spacer"></div>
            <form method="GET" class="filters">
                <div class="field"><label>From</label><input type="date" name="from" value="{{ request('from') }}"></div>
                <div class="field"><label>To</label><input type="date" name="to" value="{{ request('to') }}"></div>
                <button type="submit" class="btn sm secondary">Apply</button>
            </form>
        </div>
        <div class="card-body tight table-wrap">
            <table class="data">
                <thead><tr><th>Reference</th><th>Owner</th><th>Shop</th><th>Block</th><th>Date</th><th>Status</th></tr></thead>
                <tbody>
                @forelse ($history as $allocation)
                    <tr>
                        <td><a href="{{ route('admin.allocations.show', $allocation) }}" class="mono">{{ $allocation->reference_number }}</a></td>
                        <td>{{ $allocation->owner?->full_name }}</td>
                        <td>{{ $allocation->shop?->shop_number }}</td>
                        <td>{{ $allocation->shop?->block?->name ?? '—' }}</td>
                        <td class="nowrap">{{ $allocation->allocation_date?->toFormattedDateString() }}</td>
                        <td>@include('partials.status-badge', ['status' => $allocation->status])</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">No allocations in this period.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
