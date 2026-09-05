@extends('layouts.app')
@section('title', 'Shop '.$shop->shop_number)

@section('content')
    <div class="page-head">
        <div><h1>Shop {{ $shop->shop_number }}</h1>
            <p class="sub">{{ $shop->mall?->name }} &middot; {{ $shop->block?->name ?: 'No block' }} @if ($shop->floor) &middot; {{ $shop->floor->name }} @endif</p></div>
        <div class="spacer"></div>
        @can(\App\Support\Permissions::SHOPS_MANAGE)
            <a href="{{ route('admin.shops.edit', $shop) }}" class="btn secondary">Edit</a>
        @endcan
        @can(\App\Support\Permissions::ALLOCATIONS_CREATE)
            @if ($shop->isAllocatable())
                <a href="{{ route('admin.allocations.create', ['shop_id' => $shop->id]) }}" class="btn">Allocate this shop</a>
            @endif
        @endcan
    </div>

    @if (! $shop->isAllocatable())
        {{-- FR-032 - the shop is not available for a further allocation. --}}
        <div class="alert info">
            This shop is currently held under allocation
            <a href="{{ route('admin.allocations.show', $activeAllocation) }}" class="mono">{{ $activeAllocation?->reference_number }}</a>
            and cannot be allocated again until that allocation is cancelled or transferred.
        </div>
    @endif

    <div class="grid cols-2">
        <div class="card"><div class="card-head"><h2>Shop details</h2></div><div class="card-body">
            <dl class="detail">
                <dt>Shop ID</dt><dd class="mono">{{ $shop->shop_code }}</dd>
                <dt>Type</dt><dd>{{ str($shop->shop_type)->title() }}</dd>
                <dt>Size</dt><dd>{{ $shop->size_sqm ? $shop->size_sqm.' m²' : '—' }}</dd>
                <dt>Status</dt><dd>@include('partials.status-badge', ['status' => $shop->status])</dd>
                <dt>Annual rent</dt><dd>@include('partials.money', ['amount' => $shop->annual_rent])</dd>
                <dt>Service charge</dt><dd>@include('partials.money', ['amount' => $shop->service_charge])</dd>
                <dt>Other fees</dt><dd>@include('partials.money', ['amount' => $shop->other_fees])</dd>
                <dt>Description</dt><dd>{{ $shop->description ?: '—' }}</dd>
            </dl>
        </div></div>

        <div class="card"><div class="card-head"><h2>Current allocation</h2></div><div class="card-body">
            @if ($activeAllocation)
                <dl class="detail">
                    <dt>Reference</dt><dd><a href="{{ route('admin.allocations.show', $activeAllocation) }}" class="mono">{{ $activeAllocation->reference_number }}</a></dd>
                    <dt>Owner</dt><dd><a href="{{ route('admin.owners.show', $activeAllocation->owner) }}">{{ $activeAllocation->owner?->full_name }}</a></dd>
                    <dt>Status</dt><dd>@include('partials.status-badge', ['status' => $activeAllocation->status])</dd>
                    <dt>Allocated</dt><dd>{{ $activeAllocation->allocation_date?->toFormattedDateString() }}</dd>
                </dl>
            @else
                <p class="muted mb0">This shop is not currently allocated.</p>
            @endif
        </div></div>
    </div>

    {{-- FR-123 - the permanent ownership history of the shop. --}}
    <div class="card">
        <div class="card-head"><h2>Ownership history</h2>
            <span class="badge muted">Retained permanently</span></div>
        <div class="card-body tight table-wrap">
            <table class="data">
                <thead><tr><th>Reference</th><th>Owner</th><th>From</th><th>Status</th><th>Closed</th><th>Reason</th></tr></thead>
                <tbody>
                @forelse ($history as $allocation)
                    <tr>
                        <td><a href="{{ route('admin.allocations.show', $allocation) }}" class="mono">{{ $allocation->reference_number }}</a></td>
                        <td>{{ $allocation->owner?->full_name }}</td>
                        <td class="nowrap">{{ $allocation->allocation_date?->toFormattedDateString() }}</td>
                        <td>@include('partials.status-badge', ['status' => $allocation->status])</td>
                        <td class="nowrap">{{ $allocation->cancelled_at?->toFormattedDateString() ?? '—' }}</td>
                        <td class="small">{{ $allocation->cancellation_reason ?: '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">This shop has never been allocated.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
