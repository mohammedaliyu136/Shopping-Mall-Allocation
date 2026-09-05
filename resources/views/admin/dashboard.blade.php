@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')
    <div class="page-head">
        <div>
            <h1>Dashboard</h1>
            <p class="sub">{{ now()->toFormattedDayDateString() }}</p>
        </div>
    </div>

    <div class="grid cols-4">
        <div class="stat"><div class="label">Shops</div><div class="value">{{ $stats['shops'] }}</div>
            <div class="note">{{ $stats['available_shops'] }} available</div></div>
        <div class="stat"><div class="label">Occupancy</div><div class="value">{{ $stats['occupancy_rate'] }}%</div>
            <div class="note">{{ $stats['allocated_shops'] }} allocated</div></div>
        <div class="stat"><div class="label">Owners</div><div class="value">{{ $stats['owners'] }}</div>
            <div class="note">{{ $stats['active_allocations'] }} active allocations</div></div>
        <div class="stat"><div class="label">Open Tickets</div><div class="value">{{ $stats['open_tickets'] }}</div>
            <div class="note">awaiting resolution</div></div>
    </div>

    <div class="grid cols-3 mt">
        <div class="stat"><div class="label">Revenue Collected</div>
            <div class="value sm">@include('partials.money', ['amount' => $stats['revenue_collected']])</div></div>
        <div class="stat"><div class="label">Outstanding Balance</div>
            <div class="value sm">@include('partials.money', ['amount' => $stats['outstanding_balance']])</div>
            <div class="note">{{ $stats['overdue_invoices'] }} overdue invoice(s)</div></div>
        <div class="stat"><div class="label">Awaiting Action</div>
            <div class="value sm">{{ $stats['pending_approvals'] + $stats['pending_transfers'] }}</div>
            <div class="note">{{ $stats['pending_approvals'] }} allocations, {{ $stats['pending_transfers'] }} transfers</div></div>
    </div>

    <div class="grid cols-2 mt">
        <div class="card">
            <div class="card-head"><h2>Allocations awaiting approval</h2><div class="spacer"></div>
                <a href="{{ route('admin.allocations.index', ['status' => 'pending_approval']) }}" class="small">View all</a></div>
            <div class="card-body tight table-wrap">
                <table class="data">
                    <thead><tr><th>Reference</th><th>Shop</th><th>Owner</th></tr></thead>
                    <tbody>
                    @forelse ($pendingAllocations as $allocation)
                        <tr>
                            <td><a href="{{ route('admin.allocations.show', $allocation) }}" class="mono">{{ $allocation->reference_number }}</a></td>
                            <td>{{ $allocation->shop?->label() }}</td>
                            <td>{{ $allocation->owner?->full_name }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="empty">Nothing awaiting approval.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-head"><h2>Overdue invoices</h2><div class="spacer"></div>
                <a href="{{ route('admin.invoices.index', ['status' => 'overdue']) }}" class="small">View all</a></div>
            <div class="card-body tight table-wrap">
                <table class="data">
                    <thead><tr><th>Invoice</th><th>Owner</th><th class="num">Balance</th><th>Due</th></tr></thead>
                    <tbody>
                    @forelse ($overdueInvoices as $invoice)
                        <tr>
                            <td><a href="{{ route('admin.invoices.show', $invoice) }}" class="mono">{{ $invoice->invoice_number }}</a></td>
                            <td>{{ $invoice->owner?->full_name }}</td>
                            <td class="num">@include('partials.money', ['amount' => $invoice->balance])</td>
                            <td class="nowrap">{{ $invoice->due_date->toFormattedDateString() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="empty">No overdue invoices.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-head"><h2>Transfer requests</h2><div class="spacer"></div>
                <a href="{{ route('admin.transfers.index') }}" class="small">View all</a></div>
            <div class="card-body tight table-wrap">
                <table class="data">
                    <thead><tr><th>Reference</th><th>Shop</th><th>From &rarr; To</th></tr></thead>
                    <tbody>
                    @forelse ($pendingTransfers as $transfer)
                        <tr>
                            <td><a href="{{ route('admin.transfers.show', $transfer) }}" class="mono">{{ $transfer->reference_number }}</a></td>
                            <td>{{ $transfer->shop?->label() }}</td>
                            <td class="small">{{ $transfer->fromOwner?->full_name }} &rarr; {{ $transfer->toOwner?->full_name }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="empty">No pending transfer requests.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-head"><h2>Open support tickets</h2><div class="spacer"></div>
                <a href="{{ route('admin.tickets.index') }}" class="small">View all</a></div>
            <div class="card-body tight table-wrap">
                <table class="data">
                    <thead><tr><th>Ticket</th><th>Subject</th><th>Status</th></tr></thead>
                    <tbody>
                    @forelse ($openTickets as $ticket)
                        <tr>
                            <td><a href="{{ route('admin.tickets.show', $ticket) }}" class="mono">{{ $ticket->ticket_number }}</a></td>
                            <td>{{ $ticket->subject }}</td>
                            <td>@include('partials.status-badge', ['status' => $ticket->status])</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="empty">No open tickets.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
