@extends('layouts.app')
@section('title', $allocation->reference_number)

@section('content')
    <div class="page-head">
        <div><h1 class="mono">{{ $allocation->reference_number }}</h1>
            <p class="sub">{{ $allocation->shop?->label() }}</p></div>
        <div class="spacer"></div>
        @include('partials.status-badge', ['status' => $allocation->status])
    </div>

    <div class="grid cols-2">
        <div class="card"><div class="card-head"><h2>Allocation</h2></div><div class="card-body">
            <dl class="detail">
                <dt>Mall</dt><dd>{{ $allocation->mall?->name }}</dd>
                <dt>Shop</dt><dd>{{ $allocation->shop?->label() }}</dd>
                <dt>Allocated</dt><dd>{{ $allocation->allocation_date?->toFormattedDateString() }}</dd>
                <dt>Expires</dt><dd>{{ $allocation->expiry_date?->toFormattedDateString() ?? '—' }}</dd>
                <dt>Type</dt><dd>{{ str($allocation->allocation_type)->replace('_',' ')->title() }}</dd>
                <dt>Annual rent</dt><dd>@include('partials.money', ['amount' => $allocation->annual_rent])</dd>
                <dt>Service charge</dt><dd>@include('partials.money', ['amount' => $allocation->service_charge])</dd>
                <dt>Other fees</dt><dd>@include('partials.money', ['amount' => $allocation->other_fees])</dd>
                <dt>Total per year</dt><dd><strong>@include('partials.money', ['amount' => $allocation->totalAnnualCharges()])</strong></dd>
            </dl>
        </div></div>

        <div class="card"><div class="card-head"><h2>Documents</h2></div>
            <div class="card-body" style="text-align:center">
                <span class="qr">{!! $qr !!}</span>
                <p class="small muted mt">Anyone can verify this allocation by scanning the code above.</p>
                <div class="actions" style="justify-content:center">
                    @if ($allocation->letter)
                        <a href="{{ route('documents.letter', $allocation) }}" class="btn secondary">Allocation letter</a>
                    @endif
                    @if ($allocation->cards->where('status', 'active')->isNotEmpty())
                        <a href="{{ route('documents.card', $allocation) }}" class="btn secondary">Allocation card</a>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h2>Invoices for this shop</h2></div>
        <div class="card-body tight table-wrap">
            <table class="data">
                <thead><tr><th>Invoice</th><th>Period</th><th>Due</th><th class="num">Total</th><th class="num">Balance</th><th>Status</th></tr></thead>
                <tbody>
                @forelse ($allocation->invoices as $invoice)
                    <tr>
                        <td><a href="{{ route('portal.invoices.show', $invoice) }}" class="mono">{{ $invoice->invoice_number }}</a></td>
                        <td class="small nowrap">{{ $invoice->billing_period_start->format('M Y') }} – {{ $invoice->billing_period_end->format('M Y') }}</td>
                        <td class="nowrap">{{ $invoice->due_date->toFormattedDateString() }}</td>
                        <td class="num">@include('partials.money', ['amount' => $invoice->total_amount])</td>
                        <td class="num">@include('partials.money', ['amount' => $invoice->balance])</td>
                        <td>@include('partials.status-badge', ['status' => $invoice->status])</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">No invoices raised for this shop.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($allocation->terms)
        <div class="card"><div class="card-head"><h2>Terms and conditions</h2></div>
            <div class="card-body"><p class="small mb0" style="white-space:pre-line">{{ $allocation->terms }}</p></div></div>
    @endif
@endsection
