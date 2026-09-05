@extends('layouts.app')
@section('title', $owner->full_name)

@section('content')
    <div class="page-head">
        <div><h1>{{ $owner->full_name }}</h1>
            <p class="sub mono">{{ $owner->owner_code }} &middot; {{ $owner->business_name ?: 'Individual' }}</p></div>
        <div class="spacer"></div>
        @can(\App\Support\Permissions::OWNERS_EDIT)
            <a href="{{ route('admin.owners.edit', $owner) }}" class="btn secondary">Edit</a>
        @endcan
        @can(\App\Support\Permissions::ALLOCATIONS_CREATE)
            <a href="{{ route('admin.allocations.create') }}" class="btn">New allocation</a>
        @endcan
    </div>

    <div class="grid cols-4">
        <div class="stat"><div class="label">Status</div><div class="value sm">@include('partials.status-badge', ['status' => $owner->status])</div></div>
        <div class="stat"><div class="label">Allocations</div><div class="value">{{ $owner->allocations->count() }}</div></div>
        <div class="stat"><div class="label">Outstanding</div>
            <div class="value sm">@include('partials.money', ['amount' => $outstanding])</div></div>
        <div class="stat"><div class="label">Portal account</div>
            <div class="value sm">{{ $owner->user ? 'Active' : 'None' }}</div></div>
    </div>

    <div class="grid cols-2 mt">
        <div class="card"><div class="card-head"><h2>Personal &amp; contact</h2></div><div class="card-body">
            <dl class="detail">
                <dt>Gender</dt><dd>{{ $owner->gender ? ucfirst($owner->gender) : '—' }}</dd>
                <dt>Date of birth</dt><dd>{{ $owner->date_of_birth?->toFormattedDateString() ?: '—' }}</dd>
                <dt>Nationality</dt><dd>{{ $owner->nationality ?: '—' }}</dd>
                <dt>Phone</dt><dd>{{ $owner->phone }}</dd>
                <dt>Email</dt><dd>{{ $owner->email ?: '—' }}</dd>
                <dt>Address</dt><dd>{{ $owner->address ?: '—' }}</dd>
                <dt>Identification</dt><dd>{{ $owner->id_type ? str($owner->id_type)->replace('_',' ')->title().' — '.$owner->id_number : '—' }}</dd>
            </dl>
        </div></div>

        <div class="card"><div class="card-head"><h2>Next of kin &amp; business</h2></div><div class="card-body">
            <dl class="detail">
                <dt>Next of kin</dt><dd>{{ $owner->nok_name ?: '—' }}</dd>
                <dt>Relationship</dt><dd>{{ $owner->nok_relationship ?: '—' }}</dd>
                <dt>NOK phone</dt><dd>{{ $owner->nok_phone ?: '—' }}</dd>
                <dt>NOK address</dt><dd>{{ $owner->nok_address ?: '—' }}</dd>
                <dt>Business</dt><dd>{{ $owner->business_name ?: '—' }}</dd>
                <dt>Registration</dt><dd>{{ $owner->business_reg_number ?: '—' }}</dd>
                <dt>Nature</dt><dd>{{ $owner->business_nature ?: '—' }}</dd>
            </dl>
        </div></div>
    </div>

    <div class="card">
        <div class="card-head"><h2>Allocations</h2></div>
        <div class="card-body tight table-wrap">
            <table class="data">
                <thead><tr><th>Reference</th><th>Shop</th><th>Date</th><th>Status</th></tr></thead>
                <tbody>
                @forelse ($owner->allocations as $allocation)
                    <tr>
                        <td><a href="{{ route('admin.allocations.show', $allocation) }}" class="mono">{{ $allocation->reference_number }}</a></td>
                        <td>{{ $allocation->shop?->label() }}</td>
                        <td class="nowrap">{{ $allocation->allocation_date?->toFormattedDateString() }}</td>
                        <td>@include('partials.status-badge', ['status' => $allocation->status])</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="empty">No allocations recorded.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="grid cols-2">
        <div class="card"><div class="card-head"><h2>Recent invoices</h2></div><div class="card-body tight table-wrap">
            <table class="data">
                <thead><tr><th>Invoice</th><th class="num">Total</th><th class="num">Balance</th><th>Status</th></tr></thead>
                <tbody>
                @forelse ($owner->invoices as $invoice)
                    <tr>
                        <td><a href="{{ route('admin.invoices.show', $invoice) }}" class="mono">{{ $invoice->invoice_number }}</a></td>
                        <td class="num">@include('partials.money', ['amount' => $invoice->total_amount])</td>
                        <td class="num">@include('partials.money', ['amount' => $invoice->balance])</td>
                        <td>@include('partials.status-badge', ['status' => $invoice->status])</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="empty">No invoices raised.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div></div>

        <div class="card"><div class="card-head"><h2>Recent payments</h2></div><div class="card-body tight table-wrap">
            <table class="data">
                <thead><tr><th>Reference</th><th>Date</th><th class="num">Amount</th><th>Method</th></tr></thead>
                <tbody>
                @forelse ($owner->payments as $payment)
                    <tr>
                        <td><a href="{{ route('admin.payments.show', $payment) }}" class="mono">{{ $payment->payment_reference }}</a></td>
                        <td class="nowrap">{{ $payment->payment_date->toFormattedDateString() }}</td>
                        <td class="num">@include('partials.money', ['amount' => $payment->amount])</td>
                        <td>{{ str($payment->method)->replace('_',' ')->title() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="empty">No payments recorded.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div></div>
    </div>

    {{-- FR-042 - owner documents. --}}
    <div class="card">
        <div class="card-head"><h2>Documents</h2></div>
        <div class="card-body">
            <div class="table-wrap">
                <table class="data">
                    <thead><tr><th>Title</th><th>Type</th><th>Uploaded by</th><th>Date</th><th></th></tr></thead>
                    <tbody>
                    @forelse ($owner->documents as $document)
                        <tr>
                            <td>{{ $document->title }}</td>
                            <td>{{ str($document->type)->replace('_',' ')->title() }}</td>
                            <td>{{ $document->uploader?->name ?? '—' }}</td>
                            <td class="nowrap">{{ $document->created_at->toFormattedDateString() }}</td>
                            <td class="right"><a href="{{ route('admin.owners.documents.download', [$owner, $document]) }}" class="btn sm secondary">Download</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty">No documents uploaded.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            @can(\App\Support\Permissions::OWNERS_EDIT)
                <form method="POST" action="{{ route('admin.owners.documents.store', $owner) }}" enctype="multipart/form-data" class="filters mt">
                    @csrf
                    <div class="field"><label>Title</label><input type="text" name="title" required></div>
                    <div class="field"><label>Type</label>
                        <select name="type" required>
                            @foreach (['identification' => 'Identification', 'business_registration' => 'Business registration', 'allocation_agreement' => 'Allocation agreement', 'transfer' => 'Transfer', 'other' => 'Other'] as $v => $l)
                                <option value="{{ $v }}">{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field"><label>File</label><input type="file" name="file" required></div>
                    <button type="submit" class="btn sm">Upload</button>
                </form>
            @endcan
        </div>
    </div>
@endsection
