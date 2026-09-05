@extends('layouts.app')
@section('title', 'Record payment')

@section('content')
    <div class="page-head">
        <div><h1>Record payment</h1>
            <p class="sub">Invoice <span class="mono">{{ $invoice->invoice_number }}</span> &middot; {{ $invoice->owner?->full_name }}</p></div>
    </div>

    <div class="grid cols-3">
        <div class="stat"><div class="label">Invoice total</div><div class="value sm">@include('partials.money', ['amount' => $invoice->total_amount])</div></div>
        <div class="stat"><div class="label">Already paid</div><div class="value sm">@include('partials.money', ['amount' => $invoice->amount_paid])</div></div>
        <div class="stat"><div class="label">Outstanding</div><div class="value sm">@include('partials.money', ['amount' => $invoice->balance])</div></div>
    </div>

    <div class="card mt" style="max-width:620px"><div class="card-body">
        <form method="POST" action="{{ route('admin.payments.store', $invoice) }}">
            @csrf
            <div class="grid cols-2">
                <div class="field"><label for="amount">Amount</label>
                    <input id="amount" type="number" step="0.01" min="0.01" max="{{ $invoice->balance }}"
                           name="amount" value="{{ old('amount', $invoice->balance) }}" required>
                    <div class="hint">Partial payments are accepted; the balance is recalculated automatically.</div></div>
                <div class="field"><label for="payment_date">Payment date</label>
                    <input id="payment_date" type="date" name="payment_date" value="{{ old('payment_date', now()->toDateString()) }}" required></div>
            </div>

            <div class="grid cols-2">
                <div class="field"><label for="method">Method</label>
                    <select id="method" name="method" required>
                        @foreach (\App\Models\Payment::METHODS as $method)
                            <option value="{{ $method }}" @selected(old('method') === $method)>{{ str($method)->replace('_',' ')->title() }}</option>
                        @endforeach
                    </select></div>
                <div class="field"><label for="payer_name">Payer</label>
                    <input id="payer_name" type="text" name="payer_name" value="{{ old('payer_name', $invoice->owner?->full_name) }}"></div>
            </div>

            <div class="field"><label for="gateway_reference">Bank / gateway reference</label>
                <input id="gateway_reference" type="text" name="gateway_reference" value="{{ old('gateway_reference') }}">
                <div class="hint">Recorded once only — a reference already on file is refused, preventing duplicate entries.</div></div>

            <div class="field"><label for="notes">Notes</label>
                <textarea id="notes" name="notes">{{ old('notes') }}</textarea></div>

            <div class="actions">
                <button type="submit" class="btn">Record payment &amp; issue receipt</button>
                <a href="{{ route('admin.invoices.show', $invoice) }}" class="btn secondary">Cancel</a>
            </div>
        </form>
    </div></div>
@endsection
