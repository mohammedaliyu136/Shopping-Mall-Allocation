@extends('layouts.app')
@section('title', 'Shops')

@section('content')
    <div class="page-head">
        <div><h1>Shops</h1><p class="sub">Commercial units and their allocation status (FR-030, FR-031).</p></div>
        <div class="spacer"></div>
        @can(\App\Support\Permissions::SHOPS_MANAGE)
            <a href="{{ route('admin.shops.create') }}" class="btn">Register shop</a>
        @endcan
    </div>

    <div class="card"><div class="card-body">
        <form method="GET" class="filters">
            <div class="field"><label>Search</label><input type="search" name="q" value="{{ request('q') }}" placeholder="Shop number or code"></div>
            <div class="field"><label>Status</label>
                <select name="status">
                    <option value="">All</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ str($status)->title() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field"><label>Block</label>
                <select name="block">
                    <option value="">All</option>
                    @foreach ($blocks as $block)
                        <option value="{{ $block->id }}" @selected(request('block') == $block->id)>{{ $block->mall?->code }} — {{ $block->name }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn secondary">Filter</button>
            <a href="{{ route('admin.shops.index') }}" class="btn secondary">Reset</a>
        </form>
    </div></div>

    <div class="card"><div class="card-body tight table-wrap">
        <table class="data">
            <thead><tr>
                <th>Shop</th><th>Block / Floor</th><th>Type</th><th class="num">Annual rent</th>
                <th>Status</th><th>Current owner</th><th></th>
            </tr></thead>
            <tbody>
            @forelse ($shops as $shop)
                <tr>
                    <td><a href="{{ route('admin.shops.show', $shop) }}" class="mono">{{ $shop->shop_number }}</a></td>
                    <td class="small">{{ $shop->block?->name ?: '—' }} @if ($shop->floor) / {{ $shop->floor->name }} @endif</td>
                    <td>{{ str($shop->shop_type)->title() }}</td>
                    <td class="num">@include('partials.money', ['amount' => $shop->annual_rent])</td>
                    <td>@include('partials.status-badge', ['status' => $shop->status])</td>
                    <td>{{ $shop->activeAllocation?->owner?->full_name ?? '—' }}</td>
                    <td class="right">
                        @can(\App\Support\Permissions::ALLOCATIONS_CREATE)
                            @if ($shop->isAllocatable())
                                <a href="{{ route('admin.allocations.create', ['shop_id' => $shop->id]) }}" class="btn sm">Allocate</a>
                            @endif
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty">No shops match these filters.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div></div>
    {{ $shops->links() }}
@endsection
