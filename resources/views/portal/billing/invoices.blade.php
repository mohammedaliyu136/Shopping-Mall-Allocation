@extends('layouts.app')
@section('title', 'My invoices')

@section('content')
    <div class="page-head">
        <div><h1>My invoices</h1></div>
        <div class="spacer"></div>
        <div class="stat"><div class="label">Outstanding</div>
            <div class="value sm">@include('partials.money', ['amount' => $outstanding])</div></div>
    </div>

    <div class="card"><div class="card-body">
        <form method="GET" class="filters">
            <div class="field"><label>Status</label>
                <select name="status">
                    <option value="">All</option>
                    @foreach (['unpaid', 'partially_paid', 'paid', 'overdue', 'cancelled'] as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ str($status)->replace('_',' ')->title() }}</option>
                    @endforeach
                </select></div>
            <button type="submit" class="btn secondary">Filter</button>
        </form>
    </div></div>

    <div class="card"><div class="card-body tight table-wrap">
        <table class="data">
            <thead><tr><th>Invoice</th><th>Shop</th><th>Period</th><th>Due</th>
                <th class="num">Total</th><th class="num">Balance</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse ($invoices as $invoice)
                <tr>
                    <td><a href="{{ route('portal.invoices.show', $invoice) }}" class="mono">{{ $invoice->invoice_number }}</a></td>
                    <td>{{ $invoice->shop?->label() }}</td>
                    <td class="small nowrap">{{ $invoice->billing_period_start->format('M Y') }} – {{ $invoice->billing_period_end->format('M Y') }}</td>
                    <td class="nowrap">{{ $invoice->due_date->toFormattedDateString() }}</td>
                    <td class="num">@include('partials.money', ['amount' => $invoice->total_amount])</td>
                    <td class="num">@include('partials.money', ['amount' => $invoice->balance])</td>
                    <td>@include('partials.status-badge', ['status' => $invoice->status])</td>
                    <td class="right"><a href="{{ route('documents.invoice', $invoice) }}" class="btn sm secondary">PDF</a></td>
                </tr>
            @empty
                <tr><td colspan="8" class="empty">You have no invoices.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div></div>
    {{ $invoices->links() }}
@endsection
