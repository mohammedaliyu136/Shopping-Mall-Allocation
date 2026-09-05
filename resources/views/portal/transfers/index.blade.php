@extends('layouts.app')
@section('title', 'Ownership transfers')

@section('content')
    <div class="page-head">
        <div><h1>Ownership transfers</h1><p class="sub">Requests you have raised or that name you as the incoming owner.</p></div>
        <div class="spacer"></div>
        <a href="{{ route('portal.transfers.create') }}" class="btn">Request a transfer</a>
    </div>

    <div class="card"><div class="card-body tight table-wrap">
        <table class="data">
            <thead><tr><th>Reference</th><th>Shop</th><th>From</th><th>To</th><th>Status</th><th>Requested</th></tr></thead>
            <tbody>
            @forelse ($transfers as $transfer)
                <tr>
                    <td class="mono">{{ $transfer->reference_number }}</td>
                    <td>{{ $transfer->shop?->label() }}</td>
                    <td>{{ $transfer->fromOwner?->full_name }}</td>
                    <td>{{ $transfer->toOwner?->full_name }}</td>
                    <td>@include('partials.status-badge', ['status' => $transfer->status])</td>
                    <td class="nowrap small">{{ $transfer->created_at->toFormattedDateString() }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">No transfer requests.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div></div>
    {{ $transfers->links() }}
@endsection
