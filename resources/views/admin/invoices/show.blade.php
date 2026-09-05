@extends('layouts.app')
@section('title', $invoice->invoice_number)

@section('content')
    <div class="page-head">
        <div><h1 class="mono">{{ $invoice->invoice_number }}</h1>
            <p class="sub">{{ $invoice->owner?->full_name }} &middot; {{ $invoice->shop?->label() }}</p></div>
        <div class="spacer"></div>
        @include('partials.status-badge', ['status' => $invoice->status])
        <a href="{{ route('documents.invoice', $invoice) }}" class="btn secondary">Download PDF</a>
        @can(\App\Support\Permissions::PAYMENTS_RECORD)
            @if (in_array($invoice->status, \App\Models\Invoice::OPEN_STATUSES, true))
                <a href="{{ route('admin.payments.create', $invoice) }}" class="btn">Record payment</a>
            @endif
        @endcan
    </div>

    <div class="grid cols-4">
        <div class="stat"><div class="label">Total</div><div class="value sm">@include('partials.money', ['amount' => $invoice->total_amount])</div></div>
        <div class="stat"><div class="label">Paid</div><div class="value sm">@include('partials.money', ['amount' => $invoice->amount_paid])</div></div>
        <div class="stat"><div class="label">Balance</div><div class="value sm">@include('partials.money', ['amount' => $invoice->balance])</div></div>
        <div class="stat"><div class="label">Due date</div><div class="value sm">{{ $invoice->due_date->format('d M Y') }}</div>
            @if ($invoice->isOverdue())<div class="note" style="color:var(--bad)">Overdue by {{ $invoice->due_date->diffInDays(now()) }} day(s)</div>@endif</div>
    </div>

    <div class="card mt">
        <div class="card-head"><h2>Fees</h2></div>
        <div class="card-body tight table-wrap">
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
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h2>Payments</h2></div>
        <div class="card-body tight table-wrap">
            <table class="data">
                <thead><tr><th>Reference</th><th>Date</th><th class="num">Amount</th><th>Method</th><th>Status</th><th>Receipt</th></tr></thead>
                <tbody>
                @forelse ($invoice->payments as $payment)
                    <tr>
                        <td><a href="{{ route('admin.payments.show', $payment) }}" class="mono">{{ $payment->payment_reference }}</a></td>
                        <td class="nowrap">{{ $payment->payment_date->toFormattedDateString() }}</td>
                        <td class="num">@include('partials.money', ['amount' => $payment->amount])</td>
                        <td>{{ str($payment->method)->replace('_',' ')->title() }}</td>
                        <td>@include('partials.status-badge', ['status' => $payment->status])</td>
                        <td>@if ($payment->receipt)
                                <a href="{{ route('documents.receipt', $payment->receipt) }}" class="mono">{{ $payment->receipt->receipt_number }}</a>
                            @else — @endif</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">No payments recorded against this invoice.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @can(\App\Support\Permissions::INVOICES_GENERATE)
        @if ($invoice->status !== \App\Models\Invoice::CANCELLED && $invoice->confirmedPayments->isEmpty())
            <div class="card"><div class="card-body">
                <form method="POST" action="{{ route('admin.invoices.cancel', $invoice) }}" class="filters">
                    @csrf
                    <div class="field" style="min-width:260px"><label>Cancel this invoice</label>
                        <input type="text" name="reason" placeholder="Reason for cancellation" required></div>
                    <button type="submit" class="btn danger sm">Cancel invoice</button>
                </form>
            </div></div>
        @endif
    @endcan
@endsection
