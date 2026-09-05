@extends('layouts.app')
@section('title', $transfer->reference_number)

@section('content')
    <div class="page-head">
        <div><h1 class="mono">{{ $transfer->reference_number }}</h1>
            <p class="sub">{{ $transfer->shop?->label() }}</p></div>
        <div class="spacer"></div>
        @include('partials.status-badge', ['status' => $transfer->status])
    </div>

    {{-- FR-121 - outstanding obligations are re-checked at approval time. --}}
    @if ($transfer->status === \App\Models\OwnershipTransfer::PENDING)
        @if (\App\Support\Money::isPositive($outstanding))
            <div class="alert bad">
                <strong>Outstanding obligations block this transfer.</strong>
                {{ $transfer->fromOwner?->full_name }} owes
                @include('partials.money', ['amount' => $outstanding]) across unsettled invoices.
                The transfer cannot be approved until the balance is cleared.
            </div>
        @else
            <div class="alert ok">All obligations for {{ $transfer->fromOwner?->full_name }} are settled. This transfer may be approved.</div>
        @endif
    @endif

    <div class="grid cols-2">
        <div class="card"><div class="card-head"><h2>Request</h2></div><div class="card-body">
            <dl class="detail">
                <dt>Shop</dt><dd><a href="{{ route('admin.shops.show', $transfer->shop) }}">{{ $transfer->shop?->label() }}</a></dd>
                <dt>Current allocation</dt><dd><a href="{{ route('admin.allocations.show', $transfer->allocation) }}" class="mono">{{ $transfer->allocation?->reference_number }}</a></dd>
                <dt>Current owner</dt><dd><a href="{{ route('admin.owners.show', $transfer->fromOwner) }}">{{ $transfer->fromOwner?->full_name }}</a></dd>
                <dt>Proposed owner</dt><dd><a href="{{ route('admin.owners.show', $transfer->toOwner) }}">{{ $transfer->toOwner?->full_name }}</a></dd>
                <dt>Reason</dt><dd>{{ $transfer->reason ?: '—' }}</dd>
                <dt>Requested by</dt><dd>{{ $transfer->requester?->name ?? '—' }} &middot; {{ $transfer->created_at->toFormattedDateString() }}</dd>
                <dt>Outstanding at request</dt><dd>@include('partials.money', ['amount' => $transfer->outstanding_at_request])</dd>
                <dt>Outstanding now</dt><dd>@include('partials.money', ['amount' => $outstanding])</dd>
                @if ($transfer->approved_at)
                    <dt>Decided by</dt><dd>{{ $transfer->approver?->name }} &middot; {{ $transfer->approved_at->toFormattedDateString() }}</dd>
                @endif
                @if ($transfer->rejection_reason)
                    <dt>Rejection reason</dt><dd>{{ $transfer->rejection_reason }}</dd>
                @endif
                @if ($transfer->newAllocation)
                    <dt>New allocation</dt>
                    <dd><a href="{{ route('admin.allocations.show', $transfer->newAllocation) }}" class="mono">{{ $transfer->newAllocation->reference_number }}</a></dd>
                @endif
            </dl>
        </div></div>

        <div class="card">
            <div class="card-head"><h2>Supporting documents</h2></div>
            <div class="card-body">
                <div class="table-wrap">
                    <table class="data">
                        <thead><tr><th>Title</th><th>Uploaded by</th><th>Date</th></tr></thead>
                        <tbody>
                        @forelse ($transfer->documents as $document)
                            <tr>
                                <td>{{ $document->title }}</td>
                                <td>{{ $document->uploader?->name ?? '—' }}</td>
                                <td class="nowrap">{{ $document->created_at->toFormattedDateString() }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="empty">No documents attached.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                @can(\App\Support\Permissions::TRANSFERS_CREATE)
                    @if ($transfer->status === \App\Models\OwnershipTransfer::PENDING)
                        <form method="POST" action="{{ route('admin.transfers.documents.store', $transfer) }}" enctype="multipart/form-data" class="filters mt">
                            @csrf
                            <div class="field"><label>Title</label><input type="text" name="title" required></div>
                            <div class="field"><label>File</label><input type="file" name="file" required></div>
                            <button type="submit" class="btn sm">Upload</button>
                        </form>
                    @endif
                @endcan
            </div>
        </div>
    </div>

    @can(\App\Support\Permissions::TRANSFERS_APPROVE)
        @if ($transfer->status === \App\Models\OwnershipTransfer::PENDING)
            <div class="card"><div class="card-head"><h2>Decision</h2></div><div class="card-body">
                <p class="small muted">
                    Approving closes the current allocation as <em>transferred</em> — it is never deleted — and issues a new
                    allocation, allocation letter, allocation card and QR verification record for the incoming owner.
                </p>
                <div class="actions">
                    <form method="POST" action="{{ route('admin.transfers.approve', $transfer) }}">
                        @csrf<button type="submit" class="btn">Approve transfer</button>
                    </form>
                    <form method="POST" action="{{ route('admin.transfers.reject', $transfer) }}" class="filters">
                        @csrf
                        <div class="field"><input type="text" name="reason" placeholder="Reason for rejection" required></div>
                        <button type="submit" class="btn danger">Reject</button>
                    </form>
                </div>
            </div></div>
        @endif
    @endcan
@endsection
