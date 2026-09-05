@extends('layouts.app')
@section('title', $invoice->invoice_number)

@section('content')
    <div class="page-head">
        <div><h1 class="mono">{{ $invoice->invoice_number }}</h1>
            <p class="sub">{{ $invoice->shop?->label() }}</p></div>
        <div class="spacer"></div>
        @include('partials.status-badge', ['status' => $invoice->status])
        <a href="{{ route('documents.invoice', $invoice) }}" class="btn secondary">Download PDF</a>
    </div>

    <div class="grid cols-4">
        <div class="stat"><div class="label">Total</div><div class="value sm">@include('partials.money', ['amount' => $invoice->total_amount])</div></div>
        <div class="stat"><div class="label">Paid</div><div class="value sm">@include('partials.money', ['amount' => $invoice->amount_paid])</div></div>
        <div class="stat"><div class="label">Balance</div><div class="value sm">@include('partials.money', ['amount' => $invoice->balance])</div></div>
        <div class="stat"><div class="label">Due date</div><div class="value sm">{{ $invoice->due_date->format('d M Y') }}</div></div>
    </div>

    <div class="card mt"><div class="card-head"><h2>Fees</h2></div><div class="card-body tight table-wrap">
        <table class="data">
            <thead><tr><th>Category</th><th>Description</th><th class="num">Qty</th><th class="num">Unit</th><th class="num">Amount</th></tr></thead>
            <tbody>
            @foreach ($invoice->items as $item)
                <tr>
                    <td>{{ $item->billingCategory?->name ?? '—' }}</td>
                    <td>{{ $item->description }}</td>
                    <td class="num">{{ rtrim(rtrim($item->quantity, '0'), '.') }}</td>
                    <td class="num">@include('partials.money', ['amount' => $item->unit_amount])</td>
                    <td class="num">@include('partials.money', ['amount' => $item->amount])</td>
                </tr>
            @endforeach
            <tr><th colspan="4" class="num">Total</th><th class="num">@include('partials.money', ['amount' => $invoice->total_amount])</th></tr>
            </tbody>
        </table>
    </div></div>

    @if (in_array($invoice->status, \App\Models\Invoice::OPEN_STATUSES, true))
        {{-- FR-103 - the owner initiates an online payment. --}}
        <div class="card"><div class="card-head"><h2>Pay this invoice</h2></div><div class="card-body">
            <form method="POST" action="{{ route('portal.invoices.pay', $invoice) }}" class="filters">
                @csrf
                <div class="field"><label for="amount">Amount to pay</label>
                    <input id="amount" type="number" step="0.01" min="0.01" max="{{ $invoice->balance }}"
                           name="amount" value="{{ $invoice->balance }}" required>
                    <div class="hint">Part payments are accepted.</div></div>
                <button type="submit" class="btn">Pay online</button>
            </form>
            <p class="hint mb0">
                Your payment is submitted to the mall's payment gateway. The receipt becomes available
                once the gateway confirms the transaction.
            </p>
        </div></div>
    @endif

    <div class="card"><div class="card-head"><h2>Payments &amp; receipts</h2></div><div class="card-body tight table-wrap">
        <table class="data">
            <thead><tr><th>Reference</th><th>Date</th><th class="num">Amount</th><th>Method</th><th>Receipt</th></tr></thead>
            <tbody>
            @forelse ($invoice->confirmedPayments as $payment)
                <tr>
                    <td class="mono small">{{ $payment->payment_reference }}</td>
                    <td class="nowrap">{{ $payment->payment_date->toFormattedDateString() }}</td>
                    <td class="num">@include('partials.money', ['amount' => $payment->amount])</td>
                    <td>{{ str($payment->method)->replace('_',' ')->title() }}</td>
                    <td>@if ($payment->receipt)
                            <a href="{{ route('documents.receipt', $payment->receipt) }}" class="btn sm secondary">Download</a>
                        @else <span class="muted">—</span> @endif</td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty">No confirmed payments yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div></div>
@endsection
