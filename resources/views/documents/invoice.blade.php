{{-- SRS FR-091 - the invoice. --}}
<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Invoice {{ $invoice->invoice_number }}</title>
@include('documents._styles')
</head>
<body>
@include('documents._letterhead', [
    'mall' => $invoice->allocation?->mall,
    'documentLabel' => 'Invoice',
    'documentNumber' => $invoice->invoice_number,
    'issuedAt' => $invoice->issue_date,
])

<div class="doc-title">Invoice</div>

@php $symbol = \App\Models\SystemSetting::get('billing.currency_symbol', ''); @endphp

<table>
    <tr>
        <td style="width:52%">
            <div class="small muted">Billed to</div>
            <strong>{{ $invoice->owner?->full_name }}</strong><br>
            @if ($invoice->owner?->business_name) {{ $invoice->owner->business_name }}<br> @endif
            {{ $invoice->owner?->address }}<br>
            {{ $invoice->owner?->phone }}
        </td>
        <td>
            <table class="detail">
                <tr><td class="k">Invoice number</td><td class="mono">{{ $invoice->invoice_number }}</td></tr>
                <tr><td class="k">Shop</td><td>{{ $invoice->shop?->label() }}</td></tr>
                <tr><td class="k">Billing period</td>
                    <td>{{ $invoice->billing_period_start->toFormattedDateString() }} –
                        {{ $invoice->billing_period_end->toFormattedDateString() }}</td></tr>
                <tr><td class="k">Issue date</td><td>{{ $invoice->issue_date->toFormattedDateString() }}</td></tr>
                <tr><td class="k">Due date</td><td><strong>{{ $invoice->due_date->toFormattedDateString() }}</strong></td></tr>
                <tr><td class="k">Status</td><td>{{ str($invoice->status)->replace('_',' ')->upper() }}</td></tr>
            </table>
        </td>
    </tr>
</table>

<table class="lines" style="margin-top:14px">
    <thead><tr>
        <th>Category</th><th>Description</th><th class="num">Qty</th>
        <th class="num">Unit</th><th class="num">Amount</th>
    </tr></thead>
    <tbody>
    @foreach ($invoice->items as $item)
        <tr>
            <td>{{ $item->billingCategory?->name ?? '—' }}</td>
            <td>{{ $item->description }}</td>
            <td class="num">{{ rtrim(rtrim($item->quantity, '0'), '.') }}</td>
            <td class="num">{{ $symbol }}{{ number_format((float) $item->unit_amount, 2) }}</td>
            <td class="num">{{ $symbol }}{{ number_format((float) $item->amount, 2) }}</td>
        </tr>
    @endforeach
    <tr class="total"><td colspan="4">Total</td>
        <td class="num">{{ $symbol }}{{ number_format((float) $invoice->total_amount, 2) }}</td></tr>
    <tr><td colspan="4" class="right">Paid to date</td>
        <td class="num">{{ $symbol }}{{ number_format((float) $invoice->amount_paid, 2) }}</td></tr>
    <tr class="total"><td colspan="4">Balance due</td>
        <td class="num">{{ $symbol }}{{ number_format((float) $invoice->balance, 2) }}</td></tr>
    </tbody>
</table>

@if ($invoice->confirmedPayments->isNotEmpty())
    <div class="doc-title">Payments received</div>
    <table class="lines">
        <thead><tr><th>Reference</th><th>Date</th><th>Method</th><th class="num">Amount</th></tr></thead>
        <tbody>
        @foreach ($invoice->confirmedPayments as $payment)
            <tr>
                <td class="mono">{{ $payment->payment_reference }}</td>
                <td>{{ $payment->payment_date->toFormattedDateString() }}</td>
                <td>{{ str($payment->method)->replace('_',' ')->title() }}</td>
                <td class="num">{{ $symbol }}{{ number_format((float) $payment->amount, 2) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endif

<div class="footnote">
    Please quote invoice number <span class="mono">{{ $invoice->invoice_number }}</span> on all payments.
    Payments may be made by cash, bank transfer, POS or through the owner portal.
    Invoices unsettled after the due date are marked overdue and may attract a penalty charge.
</div>
</body></html>
