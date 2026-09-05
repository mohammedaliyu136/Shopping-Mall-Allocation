@extends('layouts.app')
@section('title', 'Ownership transfers')

@section('content')
    <div class="page-head">
        <div><h1>Ownership transfers</h1><p class="sub">Transfer requests, obligation checks and approvals (FR-120 to FR-124).</p></div>
        <div class="spacer"></div>
        @can(\App\Support\Permissions::TRANSFERS_CREATE)
            <a href="{{ route('admin.transfers.create') }}" class="btn">New transfer request</a>
        @endcan
    </div>

    <div class="card"><div class="card-body">
        <form method="GET" class="filters">
            <div class="field"><label>Status</label>
                <select name="status">
                    <option value="">All</option>
                    @foreach (['pending', 'approved', 'rejected', 'cancelled'] as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select></div>
            <button type="submit" class="btn secondary">Filter</button>
            <a href="{{ route('admin.transfers.index') }}" class="btn secondary">Reset</a>
        </form>
    </div></div>

    <div class="card"><div class="card-body tight table-wrap">
        <table class="data">
            <thead><tr><th>Reference</th><th>Shop</th><th>Current owner</th><th>Proposed owner</th>
                <th class="num">Outstanding at request</th><th>Status</th><th>Requested</th></tr></thead>
            <tbody>
            @forelse ($transfers as $transfer)
                <tr>
                    <td><a href="{{ route('admin.transfers.show', $transfer) }}" class="mono">{{ $transfer->reference_number }}</a></td>
                    <td>{{ $transfer->shop?->label() }}</td>
                    <td>{{ $transfer->fromOwner?->full_name }}</td>
                    <td>{{ $transfer->toOwner?->full_name }}</td>
                    <td class="num">@include('partials.money', ['amount' => $transfer->outstanding_at_request])</td>
                    <td>@include('partials.status-badge', ['status' => $transfer->status])</td>
                    <td class="nowrap small">{{ $transfer->created_at->toFormattedDateString() }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty">No transfer requests recorded.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div></div>
    {{ $transfers->links() }}
@endsection
