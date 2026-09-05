@extends('layouts.app')
@section('title', 'Payments')

@section('content')
    <div class="page-head"><div><h1>Payments &amp; receipts</h1>
        <p class="sub">Recorded payments and the receipts issued against them (FR-100 to FR-111).</p></div></div>

    <div class="card"><div class="card-body">
        <form method="GET" class="filters">
            <div class="field"><label>Search</label><input type="search" name="q" value="{{ request('q') }}" placeholder="Reference or owner"></div>
            <div class="field"><label>Method</label>
                <select name="method">
                    <option value="">All</option>
                    @foreach ($methods as $method)
                        <option value="{{ $method }}" @selected(request('method') === $method)>{{ str($method)->replace('_',' ')->title() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field"><label>Status</label>
                <select name="status">
                    <option value="">All</option>
                    @foreach (['pending', 'confirmed', 'failed', 'reversed'] as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn secondary">Filter</button>
            <a href="{{ route('admin.payments.index') }}" class="btn secondary">Reset</a>
        </form>
    </div></div>

    <div class="card"><div class="card-body tight table-wrap">
        <table class="data">
            <thead><tr><th>Reference</th><th>Date</th><th>Owner</th><th>Invoice</th>
                <th class="num">Amount</th><th>Method</th><th>Status</th><th>Receipt</th><th>Received by</th></tr></thead>
            <tbody>
            @forelse ($payments as $payment)
                <tr>
                    <td><a href="{{ route('admin.payments.show', $payment) }}" class="mono">{{ $payment->payment_reference }}</a></td>
                    <td class="nowrap">{{ $payment->payment_date->toFormattedDateString() }}</td>
                    <td>{{ $payment->owner?->full_name }}</td>
                    <td class="mono small">{{ $payment->invoice?->invoice_number }}</td>
                    <td class="num">@include('partials.money', ['amount' => $payment->amount])</td>
                    <td>{{ str($payment->method)->replace('_',' ')->title() }}</td>
                    <td>@include('partials.status-badge', ['status' => $payment->status])</td>
                    <td>@if ($payment->receipt)
                            <a href="{{ route('documents.receipt', $payment->receipt) }}" class="mono small">{{ $payment->receipt->receipt_number }}</a>
                        @else — @endif</td>
                    <td class="small">{{ $payment->receiver?->name ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="9" class="empty">No payments match these filters.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div></div>
    {{ $payments->links() }}
@endsection
