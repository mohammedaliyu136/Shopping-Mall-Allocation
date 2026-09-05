{{-- SRS FR-060 / FR-061 - the allocation letter. --}}
<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Allocation Letter {{ $allocation->reference_number }}</title>
@include('documents._styles')
</head>
<body>
@include('documents._letterhead', [
    'mall' => $allocation->mall,
    'documentLabel' => 'Allocation Letter',
    'documentNumber' => $document?->document_number,
    'issuedAt' => $document?->issued_at,
])

<div class="doc-title">Letter of Allocation</div>

<p>Date: {{ $allocation->allocation_date?->toFormattedDateString() }}</p>

<p>
    <strong>{{ $allocation->owner?->full_name }}</strong><br>
    @if ($allocation->owner?->business_name) {{ $allocation->owner->business_name }}<br> @endif
    {{ $allocation->owner?->address }}<br>
    {{ $allocation->owner?->phone }}
</p>

<p>Dear {{ $allocation->owner?->full_name }},</p>

<p>
    We are pleased to confirm the allocation to you of the commercial space described below at
    <strong>{{ $allocation->mall?->name }}</strong>, subject to the terms and conditions set out in this letter.
</p>

<table class="detail">
    <tr><td class="k">Allocation reference</td><td><strong class="mono">{{ $allocation->reference_number }}</strong></td></tr>
    <tr><td class="k">Shop</td><td>{{ $allocation->shop?->label() }} (ID {{ $allocation->shop?->shop_code }})</td></tr>
    <tr><td class="k">Block / Floor</td><td>{{ $allocation->shop?->block?->name ?? '—' }}
        @if ($allocation->shop?->floor) / {{ $allocation->shop->floor->name }} @endif</td></tr>
    <tr><td class="k">Shop type / size</td><td>{{ str($allocation->shop?->shop_type)->title() }}
        @if ($allocation->shop?->size_sqm) &middot; {{ $allocation->shop->size_sqm }} m&sup2; @endif</td></tr>
    <tr><td class="k">Allocation type</td><td>{{ str($allocation->allocation_type)->replace('_',' ')->title() }}</td></tr>
    <tr><td class="k">Allocation date</td><td>{{ $allocation->allocation_date?->toFormattedDateString() }}</td></tr>
    <tr><td class="k">Expiry date</td><td>{{ $allocation->expiry_date?->toFormattedDateString() ?? 'Not applicable' }}</td></tr>
    <tr><td class="k">Owner code</td><td class="mono">{{ $allocation->owner?->owner_code }}</td></tr>
</table>

<div class="doc-title">Financial Obligations</div>
@php $symbol = \App\Models\SystemSetting::get('billing.currency_symbol', ''); @endphp
<table class="lines">
    <thead><tr><th>Charge</th><th class="num">Amount per year</th></tr></thead>
    <tbody>
        <tr><td>Annual rent</td><td class="num">{{ $symbol }}{{ number_format((float) $allocation->annual_rent, 2) }}</td></tr>
        <tr><td>Service charge</td><td class="num">{{ $symbol }}{{ number_format((float) $allocation->service_charge, 2) }}</td></tr>
        <tr><td>Other applicable fees</td><td class="num">{{ $symbol }}{{ number_format((float) $allocation->other_fees, 2) }}</td></tr>
        <tr class="total"><td>Total payable annually</td>
            <td class="num">{{ $symbol }}{{ number_format((float) $allocation->totalAnnualCharges(), 2) }}</td></tr>
    </tbody>
</table>

@if ($allocation->terms)
    <div class="doc-title">Terms and Conditions</div>
    <div class="terms">{{ $allocation->terms }}</div>
@endif

<table style="margin-top:24px">
    <tr>
        <td style="width:62%">
            <div class="signature">
                <div class="line">Authorised Signature</div>
                <div class="small muted">For and on behalf of {{ $allocation->mall?->name }}</div>
            </div>
        </td>
        <td class="center" style="width:38%">
            {{-- FR-080 - QR code linking to the public verification page. --}}
            <img src="{{ $qr }}" alt="Verification QR code" style="width:120px;height:120px">
            <div class="small muted">Scan to verify</div>
        </td>
    </tr>
</table>

<div class="footnote">
    {{-- FR-062 - unique verification reference. --}}
    This document carries the unique verification reference
    <span class="mono">{{ $document?->document_number ?? $allocation->reference_number }}</span>.
    Its authenticity and current status can be confirmed at any time at
    <span class="mono">{{ $verificationUrl }}</span>.
    A printed copy is only valid while the allocation remains active on the mall's records.
</div>
</body></html>
