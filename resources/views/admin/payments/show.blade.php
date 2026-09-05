@extends('layouts.app')
@section('title', $payment->payment_reference)

@section('content')
    <div class="page-head">
        <div><h1 class="mono">{{ $payment->payment_reference }}</h1>
            <p class="sub">{{ $payment->owner?->full_name }}</p></div>
        <div class="spacer"></div>
        @include('partials.status-badge', ['status' => $payment->status])
        @if ($payment->receipt)
            <a href="{{ route('documents.receipt', $payment->receipt) }}" class="btn secondary">Download receipt</a>
        @endif
    </div>

    <div class="grid cols-2">
        <div class="card"><div class="card-head"><h2>Payment</h2></div><div class="card-body">
            <dl class="detail">
                <dt>Amount</dt><dd><strong>@include('partials.money', ['amount' => $payment->amount])</strong></dd>
                <dt>Date</dt><dd>{{ $payment->payment_date->toFormattedDateString() }}</dd>
                <dt>Method</dt><dd>{{ str($payment->method)->replace('_',' ')->title() }}</dd>
                <dt>Payer</dt><dd>{{ $payment->payer_name ?: '—' }}</dd>
                <dt>Received by</dt><dd>{{ $payment->receiver?->name ?? '—' }}</dd>
                <dt>Gateway reference</dt><dd class="mono">{{ $payment->gateway_reference ?: '—' }}</dd>
                <dt>Notes</dt><dd>{{ $payment->notes ?: '—' }}</dd>
            </dl>
        </div></div>

        <div class="card"><div class="card-head"><h2>Invoice &amp; receipt</h2></div><div class="card-body">
            <dl class="detail">
                <dt>Invoice</dt><dd><a href="{{ route('admin.invoices.show', $payment->invoice) }}" class="mono">{{ $payment->invoice?->invoice_number }}</a></dd>
                <dt>Shop</dt><dd>{{ $payment->invoice?->shop?->label() }}</dd>
                <dt>Invoice total</dt><dd>@include('partials.money', ['amount' => $payment->invoice?->total_amount ?? 0])</dd>
                <dt>Invoice balance</dt><dd>@include('partials.money', ['amount' => $payment->invoice?->balance ?? 0])</dd>
                <dt>Receipt number</dt><dd class="mono">{{ $payment->receipt?->receipt_number ?? 'Not yet issued' }}</dd>
                <dt>Receipt issued</dt><dd>{{ $payment->receipt?->issued_at?->toDayDateTimeString() ?? '—' }}</dd>
            </dl>
        </div></div>
    </div>

    @can(\App\Support\Permissions::PAYMENTS_RECORD)
        @if ($payment->status === \App\Models\Payment::CONFIRMED)
            <div class="card"><div class="card-body">
                <form method="POST" action="{{ route('admin.payments.reverse', $payment) }}" class="filters">
                    @csrf
                    <div class="field" style="min-width:280px"><label>Reverse this payment</label>
                        <input type="text" name="reason" placeholder="Reason for reversal" required></div>
                    <button type="submit" class="btn danger sm">Reverse payment</button>
                </form>
                <p class="hint mb0">The payment record is retained for audit purposes and the invoice balance is restored.</p>
            </div></div>
        @endif
    @endcan
@endsection
