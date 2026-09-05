@extends('layouts.app')
@section('title', 'My dashboard')

@section('content')
    {{-- SRS FR-150 - owner dashboard. --}}
    <div class="page-head">
        <div><h1>Welcome, {{ $owner->full_name }}</h1>
            <p class="sub mono">{{ $owner->owner_code }}</p></div>
    </div>

    <div class="grid cols-4">
        <div class="stat"><div class="label">My shops</div><div class="value">{{ $allocations->count() }}</div></div>
        <div class="stat"><div class="label">Outstanding balance</div>
            <div class="value sm">@include('partials.money', ['amount' => $outstanding])</div></div>
        <div class="stat"><div class="label">Open bills</div><div class="value">{{ $currentBills->count() }}</div></div>
        <div class="stat"><div class="label">Unread notifications</div><div class="value">{{ $unreadCount }}</div></div>
    </div>

    <div class="card mt">
        <div class="card-head"><h2>My shops</h2><div class="spacer"></div>
            <a href="{{ route('portal.allocations.index') }}" class="small">View all</a></div>
        <div class="card-body tight table-wrap">
            <table class="data">
                <thead><tr><th>Reference</th><th>Shop</th><th>Status</th><th>Card</th><th></th></tr></thead>
                <tbody>
                @forelse ($allocations as $allocation)
                    <tr>
                        <td><a href="{{ route('portal.allocations.show', $allocation) }}" class="mono">{{ $allocation->reference_number }}</a></td>
                        <td>{{ $allocation->shop?->label() }}</td>
                        <td>@include('partials.status-badge', ['status' => $allocation->status])</td>
                        <td class="mono small">{{ $allocation->activeCard?->card_number ?? '—' }}</td>
                        <td class="right">
                            @if ($allocation->documents()->where('type', 'letter')->exists())
                                <a href="{{ route('documents.letter', $allocation) }}" class="btn sm secondary">Letter</a>
                            @endif
                            @if ($allocation->activeCard)
                                <a href="{{ route('documents.card', $allocation) }}" class="btn sm secondary">Card</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty">You have no active allocations.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="grid cols-2">
        <div class="card">
            <div class="card-head"><h2>Current bills</h2><div class="spacer"></div>
                <a href="{{ route('portal.invoices.index') }}" class="small">View all</a></div>
            <div class="card-body tight table-wrap">
                <table class="data">
                    <thead><tr><th>Invoice</th><th>Due</th><th class="num">Balance</th><th>Status</th></tr></thead>
                    <tbody>
                    @forelse ($currentBills as $invoice)
                        <tr>
                            <td><a href="{{ route('portal.invoices.show', $invoice) }}" class="mono">{{ $invoice->invoice_number }}</a></td>
                            <td class="nowrap">{{ $invoice->due_date->toFormattedDateString() }}</td>
                            <td class="num">@include('partials.money', ['amount' => $invoice->balance])</td>
                            <td>@include('partials.status-badge', ['status' => $invoice->status])</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="empty">You have no outstanding bills.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-head"><h2>Recent payments</h2><div class="spacer"></div>
                <a href="{{ route('portal.payments.index') }}" class="small">View all</a></div>
            <div class="card-body tight table-wrap">
                <table class="data">
                    <thead><tr><th>Reference</th><th>Date</th><th class="num">Amount</th><th>Receipt</th></tr></thead>
                    <tbody>
                    @forelse ($recentPayments as $payment)
                        <tr>
                            <td class="mono small">{{ $payment->payment_reference }}</td>
                            <td class="nowrap">{{ $payment->payment_date->toFormattedDateString() }}</td>
                            <td class="num">@include('partials.money', ['amount' => $payment->amount])</td>
                            <td>@if ($payment->receipt)
                                    <a href="{{ route('documents.receipt', $payment->receipt) }}" class="btn sm secondary">Download</a>
                                @else <span class="muted">—</span> @endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="empty">No payments recorded yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-head"><h2>Notifications</h2><div class="spacer"></div>
                <a href="{{ route('portal.notifications.index') }}" class="small">View all</a></div>
            <div class="card-body">
                @forelse ($notifications as $recipient)
                    <div class="message">
                        <div class="meta">{{ $recipient->created_at->toDayDateTimeString() }}
                            @unless ($recipient->read_at) &middot; <span class="badge warn">New</span> @endunless</div>
                        <strong>{{ $recipient->notification?->title }}</strong>
                        <div class="small">{{ $recipient->notification?->message }}</div>
                    </div>
                @empty
                    <p class="muted mb0">No notifications yet.</p>
                @endforelse
            </div>
        </div>

        <div class="card">
            <div class="card-head"><h2>My support tickets</h2><div class="spacer"></div>
                <a href="{{ route('portal.tickets.create') }}" class="small">Raise a ticket</a></div>
            <div class="card-body tight table-wrap">
                <table class="data">
                    <thead><tr><th>Ticket</th><th>Subject</th><th>Status</th></tr></thead>
                    <tbody>
                    @forelse ($tickets as $ticket)
                        <tr>
                            <td><a href="{{ route('portal.tickets.show', $ticket) }}" class="mono">{{ $ticket->ticket_number }}</a></td>
                            <td>{{ $ticket->subject }}</td>
                            <td>@include('partials.status-badge', ['status' => $ticket->status])</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="empty">You have not raised any tickets.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
