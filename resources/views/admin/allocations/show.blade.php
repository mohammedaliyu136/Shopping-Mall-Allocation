@extends('layouts.app')
@section('title', $allocation->reference_number)

@section('content')
    <div class="page-head">
        <div>
            <h1 class="mono">{{ $allocation->reference_number }}</h1>
            <p class="sub">{{ $allocation->shop?->label() }} &middot; {{ $allocation->owner?->full_name }}</p>
        </div>
        <div class="spacer"></div>
        @include('partials.status-badge', ['status' => $allocation->status])
    </div>

    {{-- Lifecycle actions available at this status (SRS 6.6). --}}
    <div class="card"><div class="card-body">
        <div class="actions">
            @if ($allocation->status === \App\Models\Allocation::DRAFT)
                @can(\App\Support\Permissions::ALLOCATIONS_CREATE)
                    <form method="POST" action="{{ route('admin.allocations.submit', $allocation) }}">
                        @csrf<button type="submit" class="btn">Submit for approval</button>
                    </form>
                @endcan
            @endif

            @if ($allocation->status === \App\Models\Allocation::PENDING_APPROVAL)
                @can(\App\Support\Permissions::ALLOCATIONS_APPROVE)
                    <form method="POST" action="{{ route('admin.allocations.approve', $allocation) }}" class="filters">
                        @csrf
                        <div class="field"><input type="text" name="comment" placeholder="Approval comment (optional)"></div>
                        <button type="submit" class="btn">Approve this stage</button>
                    </form>
                    <form method="POST" action="{{ route('admin.allocations.reject', $allocation) }}" class="filters">
                        @csrf
                        <div class="field"><input type="text" name="reason" placeholder="Reason for rejection" required></div>
                        <button type="submit" class="btn danger">Reject</button>
                    </form>
                @endcan
            @endif

            @if ($allocation->status === \App\Models\Allocation::APPROVED)
                @can(\App\Support\Permissions::ALLOCATIONS_APPROVE)
                    <form method="POST" action="{{ route('admin.allocations.activate', $allocation) }}">
                        @csrf<button type="submit" class="btn">Activate allocation</button>
                    </form>
                @endcan
            @endif

            @if ($allocation->status === \App\Models\Allocation::ACTIVE)
                @can(\App\Support\Permissions::ALLOCATIONS_CANCEL)
                    <form method="POST" action="{{ route('admin.allocations.suspend', $allocation) }}" class="filters">
                        @csrf
                        <div class="field"><input type="text" name="reason" placeholder="Reason for suspension" required></div>
                        <button type="submit" class="btn secondary">Suspend</button>
                    </form>
                @endcan
                @can(\App\Support\Permissions::TRANSFERS_CREATE)
                    <a href="{{ route('admin.transfers.create', ['allocation_id' => $allocation->id]) }}" class="btn secondary">Initiate transfer</a>
                @endcan
                @can(\App\Support\Permissions::INVOICES_GENERATE)
                    <a href="{{ route('admin.invoices.create', ['allocation_id' => $allocation->id]) }}" class="btn secondary">Generate invoice</a>
                @endcan
            @endif

            @if ($allocation->status === \App\Models\Allocation::SUSPENDED)
                @can(\App\Support\Permissions::ALLOCATIONS_CANCEL)
                    <form method="POST" action="{{ route('admin.allocations.reinstate', $allocation) }}">
                        @csrf<button type="submit" class="btn">Reinstate</button>
                    </form>
                @endcan
            @endif

            @if ($allocation->isLive())
                @can(\App\Support\Permissions::ALLOCATIONS_CANCEL)
                    <form method="POST" action="{{ route('admin.allocations.cancel', $allocation) }}" class="filters">
                        @csrf
                        <div class="field"><input type="text" name="reason" placeholder="Reason for cancellation" required></div>
                        <button type="submit" class="btn danger">Cancel allocation</button>
                    </form>
                @endcan
            @endif
        </div>
    </div></div>

    <div class="grid cols-2">
        <div class="card"><div class="card-head"><h2>Allocation details</h2></div><div class="card-body">
            <dl class="detail">
                <dt>Mall</dt><dd>{{ $allocation->mall?->name }}</dd>
                <dt>Shop</dt><dd><a href="{{ route('admin.shops.show', $allocation->shop) }}">{{ $allocation->shop?->label() }}</a></dd>
                <dt>Owner</dt><dd><a href="{{ route('admin.owners.show', $allocation->owner) }}">{{ $allocation->owner?->full_name }}</a></dd>
                <dt>Allocation date</dt><dd>{{ $allocation->allocation_date?->toFormattedDateString() }}</dd>
                <dt>Expiry date</dt><dd>{{ $allocation->expiry_date?->toFormattedDateString() ?: '—' }}</dd>
                <dt>Type</dt><dd>{{ str($allocation->allocation_type)->replace('_',' ')->title() }}</dd>
                <dt>Annual rent</dt><dd>@include('partials.money', ['amount' => $allocation->annual_rent])</dd>
                <dt>Service charge</dt><dd>@include('partials.money', ['amount' => $allocation->service_charge])</dd>
                <dt>Other fees</dt><dd>@include('partials.money', ['amount' => $allocation->other_fees])</dd>
                <dt>Total per year</dt><dd><strong>@include('partials.money', ['amount' => $allocation->totalAnnualCharges()])</strong></dd>
                <dt>Created by</dt><dd>{{ $allocation->creator?->name ?? '—' }}</dd>
                <dt>Approved by</dt><dd>{{ $allocation->approver?->name ?? '—' }} {{ $allocation->approved_at?->toFormattedDateString() }}</dd>
                @if ($allocation->previousAllocation)
                    <dt>Transferred from</dt>
                    <dd><a href="{{ route('admin.allocations.show', $allocation->previousAllocation) }}" class="mono">{{ $allocation->previousAllocation->reference_number }}</a>
                        ({{ $allocation->previousAllocation->owner?->full_name }})</dd>
                @endif
                @if ($allocation->cancelled_at)
                    <dt>Closed</dt><dd>{{ $allocation->cancelled_at->toFormattedDateString() }} — {{ $allocation->cancellation_reason }}</dd>
                @endif
            </dl>
        </div></div>

        {{-- FR-080 - QR verification. --}}
        <div class="card"><div class="card-head"><h2>QR verification</h2></div>
            <div class="card-body" style="text-align:center">
                <span class="qr">{!! $qr !!}</span>
                <p class="small muted mt">
                    Scanning this code opens the public verification page.<br>
                    <span class="mono" style="word-break:break-all">{{ $verificationUrl }}</span>
                </p>
                <a href="{{ $verificationUrl }}" class="btn secondary sm" target="_blank" rel="noopener">Open verification page</a>
            </div>
        </div>
    </div>

    {{-- FR-053 - the approval chain. --}}
    <div class="card">
        <div class="card-head"><h2>Approval workflow</h2></div>
        <div class="card-body">
            @if ($allocation->approvals->isEmpty())
                <p class="muted mb0">This allocation has not been submitted for approval yet.</p>
            @else
                <ul class="timeline">
                    @foreach ($allocation->approvals as $approval)
                        <li class="{{ $approval->status === 'approved' ? 'done' : ($approval->status === 'pending' ? 'current' : '') }}">
                            <strong>Level {{ $approval->level }} — {{ $approval->stage }}</strong>
                            @include('partials.status-badge', ['status' => $approval->status])
                            <div class="who">
                                {{ $approval->approver?->name ?? 'Awaiting action' }}
                                @if ($approval->acted_at) &middot; {{ $approval->acted_at->toDayDateTimeString() }} @endif
                                @if ($approval->comment) — {{ $approval->comment }} @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    {{-- FR-060 to FR-072 - generated documents and cards. --}}
    <div class="grid cols-2">
        <div class="card">
            <div class="card-head"><h2>Documents</h2></div>
            <div class="card-body">
                @if ($allocation->documents->isEmpty())
                    <p class="muted">Documents are generated automatically once the allocation is fully approved.</p>
                @else
                    <div class="table-wrap">
                        <table class="data">
                            <thead><tr><th>Document</th><th>Number</th><th>Issued</th><th></th></tr></thead>
                            <tbody>
                            @foreach ($allocation->documents as $document)
                                <tr>
                                    <td>{{ str($document->type)->title() }}</td>
                                    <td class="mono">{{ $document->document_number }}</td>
                                    <td class="nowrap">{{ $document->issued_at?->toFormattedDateString() }}</td>
                                    <td class="right">
                                        @if ($document->type === \App\Models\AllocationDocument::LETTER)
                                            <a href="{{ route('documents.letter', $allocation) }}" class="btn sm secondary">PDF</a>
                                        @else
                                            <a href="{{ route('documents.card', $allocation) }}" class="btn sm secondary">PDF</a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-head"><h2>Allocation cards</h2></div>
            <div class="card-body">
                <div class="table-wrap">
                    <table class="data">
                        <thead><tr><th>Card number</th><th>Issued</th><th>Status</th><th>Reissue of</th></tr></thead>
                        <tbody>
                        @forelse ($allocation->cards as $card)
                            <tr>
                                <td class="mono">{{ $card->card_number }}</td>
                                <td class="nowrap">{{ $card->issue_date->toFormattedDateString() }}</td>
                                <td>@include('partials.status-badge', ['status' => $card->status])</td>
                                <td class="small">{{ $card->reissueOf?->card_number ?? '—' }}
                                    @if ($card->reissue_reason)<div class="muted">{{ $card->reissue_reason }}</div>@endif</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="empty">No card issued yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- FR-072 - card reissue. --}}
                @can(\App\Support\Permissions::CARDS_REISSUE)
                    @if ($allocation->cards->isNotEmpty())
                        <form method="POST" action="{{ route('admin.allocations.cards.reissue', $allocation) }}" class="filters mt">
                            @csrf
                            <div class="field" style="min-width:220px"><label>Reissue reason</label>
                                <input type="text" name="reason" placeholder="e.g. Card reported lost" required></div>
                            <button type="submit" class="btn sm">Reissue card</button>
                        </form>
                    @endif
                @endcan
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h2>Invoices</h2><div class="spacer"></div>
            @can(\App\Support\Permissions::INVOICES_GENERATE)
                <a href="{{ route('admin.invoices.create', ['allocation_id' => $allocation->id]) }}" class="btn sm secondary">Generate invoice</a>
            @endcan
        </div>
        <div class="card-body tight table-wrap">
            <table class="data">
                <thead><tr><th>Invoice</th><th>Period</th><th class="num">Total</th><th class="num">Balance</th><th>Status</th></tr></thead>
                <tbody>
                @forelse ($allocation->invoices as $invoice)
                    <tr>
                        <td><a href="{{ route('admin.invoices.show', $invoice) }}" class="mono">{{ $invoice->invoice_number }}</a></td>
                        <td class="small nowrap">{{ $invoice->billing_period_start->format('d M Y') }} – {{ $invoice->billing_period_end->format('d M Y') }}</td>
                        <td class="num">@include('partials.money', ['amount' => $invoice->total_amount])</td>
                        <td class="num">@include('partials.money', ['amount' => $invoice->balance])</td>
                        <td>@include('partials.status-badge', ['status' => $invoice->status])</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty">No invoices raised against this allocation.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($allocation->terms)
        <div class="card"><div class="card-head"><h2>Terms and conditions</h2></div>
            <div class="card-body"><p class="small" style="white-space:pre-line">{{ $allocation->terms }}</p></div></div>
    @endif
@endsection
