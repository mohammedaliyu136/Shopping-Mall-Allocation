@extends('layouts.app')
@section('title', 'Owner report')

@section('content')
    <div class="page-head">
        <div><h1>Owner report</h1><p class="sub">FR-162</p></div>
        <div class="spacer"></div>
        <a href="{{ route('admin.reports.export', ['report' => 'owners']) }}" class="btn secondary">Export CSV</a>
    </div>

    <div class="grid cols-4">
        <div class="stat"><div class="label">Total owners</div><div class="value">{{ $report['total_owners'] }}</div></div>
        <div class="stat"><div class="label">Active</div><div class="value">{{ $report['active_owners'] }}</div></div>
        <div class="stat"><div class="label">With allocations</div><div class="value">{{ $report['owners_with_allocations'] }}</div></div>
        <div class="stat"><div class="label">With outstanding</div><div class="value">{{ $report['owners_with_outstanding'] }}</div></div>
    </div>

    <div class="card mt">
        <div class="card-head"><h2>Owners with outstanding balances</h2></div>
        <div class="card-body tight table-wrap">
            <table class="data">
                <thead><tr><th>Code</th><th>Owner</th><th>Phone</th><th>Email</th><th class="num">Outstanding</th></tr></thead>
                <tbody>
                @forelse ($outstanding as $owner)
                    <tr>
                        <td class="mono">{{ $owner->owner_code }}</td>
                        <td><a href="{{ route('admin.owners.show', $owner) }}">{{ $owner->full_name }}</a></td>
                        <td class="nowrap">{{ $owner->phone }}</td>
                        <td>{{ $owner->email ?: '—' }}</td>
                        <td class="num">@include('partials.money', ['amount' => $owner->outstanding])</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty">No owner carries an outstanding balance.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h2>Ownership transfer history</h2></div>
        <div class="card-body tight table-wrap">
            <table class="data">
                <thead><tr><th>Reference</th><th>Shop</th><th>From</th><th>To</th><th>Status</th><th>Date</th></tr></thead>
                <tbody>
                @forelse ($report['transfer_history'] as $transfer)
                    <tr>
                        <td><a href="{{ route('admin.transfers.show', $transfer) }}" class="mono">{{ $transfer->reference_number }}</a></td>
                        <td>{{ $transfer->shop?->label() }}</td>
                        <td>{{ $transfer->fromOwner?->full_name }}</td>
                        <td>{{ $transfer->toOwner?->full_name }}</td>
                        <td>@include('partials.status-badge', ['status' => $transfer->status])</td>
                        <td class="nowrap">{{ $transfer->created_at->toFormattedDateString() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">No transfers recorded.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
