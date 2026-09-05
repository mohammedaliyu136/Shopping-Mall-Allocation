{{-- SRS FR-110 - the payment receipt. --}}
@php
    $payment = $receipt->payment;
    $invoice = $payment->invoice;
    $symbol = \App\Models\SystemSetting::get('billing.currency_symbol', '');
@endphp
<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Receipt {{ $receipt->receipt_number }}</title>
@include('documents._styles')
</head>
<body>
@include('documents._letterhead', [
    'mall' => $invoice?->allocation?->mall,
    'documentLabel' => 'Official Receipt',
    'documentNumber' => $receipt->receipt_number,
    'issuedAt' => $receipt->issued_at,
])

<div class="doc-title">Official Receipt</div>

<table class="detail">
    <tr><td class="k">Receipt number</td><td><strong class="mono">{{ $receipt->receipt_number }}</strong></td></tr>
    <tr><td class="k">Received from</td><td><strong>{{ $payment->payer_name ?: $payment->owner?->full_name }}</strong></td></tr>
    <tr><td class="k">Owner</td><td>{{ $payment->owner?->full_name }} <span class="mono">({{ $payment->owner?->owner_code }})</span></td></tr>
    <tr><td class="k">Shop</td><td>{{ $invoice?->shop?->label() }}</td></tr>
    <tr><td class="k">Against invoice</td><td class="mono">{{ $invoice?->invoice_number }}</td></tr>
    <tr><td class="k">Payment reference</td><td class="mono">{{ $payment->payment_reference }}</td></tr>
    <tr><td class="k">Payment date</td><td>{{ $payment->payment_date->toFormattedDateString() }}</td></tr>
    <tr><td class="k">Payment method</td><td>{{ str($payment->method)->replace('_',' ')->title() }}</td></tr>
    @if ($payment->gateway_reference)
        <tr><td class="k">Bank / gateway reference</td><td class="mono">{{ $payment->gateway_reference }}</td></tr>
    @endif
</table>

<table class="lines" style="margin-top:14px">
    <tbody>
    <tr class="total">
        <td>Amount received</td>
        <td class="num" style="font-size:14pt">{{ $symbol }}{{ number_format((float) $payment->amount, 2) }}</td>
    </tr>
    <tr><td class="right muted">Invoice total</td>
        <td class="num">{{ $symbol }}{{ number_format((float) ($invoice?->total_amount ?? 0), 2) }}</td></tr>
    <tr><td class="right muted">Balance remaining on invoice</td>
        <td class="num">{{ $symbol }}{{ number_format((float) ($invoice?->balance ?? 0), 2) }}</td></tr>
    </tbody>
</table>

<div style="margin-top:16px">
    <span class="stamp {{ $payment->status === \App\Models\Payment::CONFIRMED ? '' : 'void' }}">
        {{ $payment->status === \App\Models\Payment::CONFIRMED ? 'PAYMENT CONFIRMED' : strtoupper($payment->status) }}
    </span>
</div>

<div class="signature">
    <div class="line">Receiving Officer</div>
    <div class="small muted">{{ $payment->receiver?->name ?? 'Mall Management' }}</div>
</div>

<div class="footnote">
    This receipt is issued electronically and is valid without a physical signature.
    Retain it as evidence of payment. Its details can be confirmed against receipt number
    <span class="mono">{{ $receipt->receipt_number }}</span> on the mall's records.
</div>
</body></html>
