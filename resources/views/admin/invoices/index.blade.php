@extends('layouts.app')
@section('title', 'Invoices')

@section('content')
    <div class="page-head">
        <div><h1>Invoices</h1><p class="sub">Billing, outstanding balances and overdue accounts (FR-091 to FR-094).</p></div>
        <div class="spacer"></div>
        @can(\App\Support\Permissions::INVOICES_GENERATE)
            <a href="{{ route('admin.invoices.create') }}" class="btn">New invoice</a>
        @endcan
    </div>

    <div class="grid cols-3">
        <div class="stat"><div class="label">Total outstanding</div>
            <div class="value sm">@include('partials.money', ['amount' => $totalOutstanding])</div></div>
        @can(\App\Support\Permissions::INVOICES_GENERATE)
            <div class="stat">
                <div class="label">Automatic annual billing</div>
                {{-- FR-092 --}}
                <form method="POST" action="{{ route('admin.invoices.run-billing') }}" class="filters mt">
                    @csrf
                    <div class="field"><input type="date" name="period_start" value="{{ now()->startOfYear()->toDateString() }}"></div>
                    <button type="submit" class="btn sm">Run billing</button>
                </form>
                <div class="note">Bills every active allocation for the period; already-billed allocations are skipped.</div>
            </div>
            <div class="stat">
                <div class="label">Overdue detection</div>
                {{-- FR-094 --}}
                <form method="POST" action="{{ route('admin.invoices.flag-overdue') }}" class="mt">
                    @csrf<button type="submit" class="btn sm secondary">Run overdue reminders</button>
                </form>
                <div class="note">Notifies owners of unsettled invoices past their due date. Each invoice is reminded about once.</div>
            </div>
        @endcan
    </div>

    <div class="card mt"><div class="card-body">
        <form method="GET" class="filters">
            <div class="field"><label>Search</label><input type="search" name="q" value="{{ request('q') }}" placeholder="Invoice number or owner"></div>
            <div class="field"><label>Status</label>
                <select name="status">
                    <option value="">All</option>
                    @foreach (['unpaid', 'partially_paid', 'paid', 'overdue', 'cancelled'] as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ str($status)->replace('_',' ')->title() }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn secondary">Filter</button>
            <a href="{{ route('admin.invoices.index') }}" class="btn secondary">Reset</a>
        </form>
    </div></div>

    <div class="card"><div class="card-body tight table-wrap">
        <table class="data">
            <thead><tr><th>Invoice</th><th>Owner</th><th>Shop</th><th>Period</th><th>Due</th>
                <th class="num">Total</th><th class="num">Paid</th><th class="num">Balance</th><th>Status</th></tr></thead>
            <tbody>
            @forelse ($invoices as $invoice)
                <tr>
                    <td><a href="{{ route('admin.invoices.show', $invoice) }}" class="mono">{{ $invoice->invoice_number }}</a></td>
                    <td>{{ $invoice->owner?->full_name }}</td>
                    <td>{{ $invoice->shop?->shop_number }}</td>
                    <td class="small nowrap">{{ $invoice->billing_period_start->format('M Y') }} – {{ $invoice->billing_period_end->format('M Y') }}</td>
                    <td class="nowrap">{{ $invoice->due_date->toFormattedDateString() }}</td>
                    <td class="num">@include('partials.money', ['amount' => $invoice->total_amount])</td>
                    <td class="num">@include('partials.money', ['amount' => $invoice->amount_paid])</td>
                    <td class="num">@include('partials.money', ['amount' => $invoice->balance])</td>
                    <td>@include('partials.status-badge', ['status' => $invoice->status])</td>
                </tr>
            @empty
                <tr><td colspan="9" class="empty">No invoices match these filters.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div></div>
    {{ $invoices->links() }}
@endsection
