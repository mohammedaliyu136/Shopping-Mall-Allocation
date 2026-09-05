@extends('layouts.app')
@section('title', 'Reports')

@section('content')
    <div class="page-head"><div><h1>Reports &amp; analytics</h1>
        <p class="sub">Allocation, financial, owner and support reporting (FR-160 to FR-163).</p></div></div>

    <div class="grid cols-4">
        <div class="stat"><div class="label">Occupancy</div><div class="value">{{ $stats['occupancy_rate'] }}%</div>
            <div class="note">{{ $stats['allocated_shops'] }} of {{ $stats['shops'] }} shops</div></div>
        <div class="stat"><div class="label">Revenue collected</div>
            <div class="value sm">@include('partials.money', ['amount' => $stats['revenue_collected']])</div></div>
        <div class="stat"><div class="label">Outstanding</div>
            <div class="value sm">@include('partials.money', ['amount' => $stats['outstanding_balance']])</div></div>
        <div class="stat"><div class="label">Open tickets</div><div class="value">{{ $stats['open_tickets'] }}</div></div>
    </div>

    <div class="grid cols-2 mt">
        <div class="card"><div class="card-head"><h2>Allocation report</h2></div><div class="card-body">
            <p class="small muted">Total, available, allocated and unallocated shops, plus allocation history.</p>
            <a href="{{ route('admin.reports.allocations') }}" class="btn">Open report</a>
        </div></div>
        <div class="card"><div class="card-head"><h2>Financial report</h2></div><div class="card-body">
            <p class="small muted">Expected revenue, collections, outstanding balances, overdue accounts and revenue by category and period.</p>
            <a href="{{ route('admin.reports.financial') }}" class="btn">Open report</a>
        </div></div>
        <div class="card"><div class="card-head"><h2>Owner report</h2></div><div class="card-body">
            <p class="small muted">Owner counts, owners carrying a balance and ownership transfer history.</p>
            <a href="{{ route('admin.reports.owners') }}" class="btn">Open report</a>
        </div></div>
        <div class="card"><div class="card-head"><h2>Support report</h2></div><div class="card-body">
            <p class="small muted">Open and resolved tickets, tickets by category and average resolution time.</p>
            <a href="{{ route('admin.reports.support') }}" class="btn">Open report</a>
        </div></div>
    </div>
@endsection
