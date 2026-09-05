@extends('layouts.app')
@section('title', 'My payments')

@section('content')
    <div class="page-head"><div><h1>Payments &amp; receipts</h1>
        <p class="sub">Download and print your receipts (FR-111).</p></div></div>

    <div class="card"><div class="card-body tight table-wrap">
        <table class="data">
            <thead><tr><th>Reference</th><th>Date</th><th>Invoice</th><th class="num">Amount</th>
                <th>Method</th><th>Status</th><th>Receipt</th></tr></thead>
            <tbody>
            @forelse ($payments as $payment)
                <tr>
                    <td class="mono small">{{ $payment->payment_reference }}</td>
                    <td class="nowrap">{{ $payment->payment_date->toFormattedDateString() }}</td>
                    <td><a href="{{ route('portal.invoices.show', $payment->invoice) }}" class="mono small">{{ $payment->invoice?->invoice_number }}</a></td>
                    <td class="num">@include('partials.money', ['amount' => $payment->amount])</td>
                    <td>{{ str($payment->method)->replace('_',' ')->title() }}</td>
                    <td>@include('partials.status-badge', ['status' => $payment->status])</td>
                    <td>@if ($payment->receipt)
                            <a href="{{ route('documents.receipt', $payment->receipt) }}" class="btn sm secondary">Download</a>
                        @else <span class="muted">—</span> @endif</td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty">No payments recorded.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div></div>
    {{ $payments->links() }}
@endsection
