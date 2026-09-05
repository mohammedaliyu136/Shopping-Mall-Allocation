@extends('layouts.app')
@section('title', 'My shops')

@section('content')
    <div class="page-head"><div><h1>My shops</h1>
        <p class="sub">Your current and past allocations.</p></div></div>

    <div class="card"><div class="card-body tight table-wrap">
        <table class="data">
            <thead><tr><th>Reference</th><th>Shop</th><th>Allocated</th><th>Expires</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse ($allocations as $allocation)
                <tr>
                    <td><a href="{{ route('portal.allocations.show', $allocation) }}" class="mono">{{ $allocation->reference_number }}</a></td>
                    <td>{{ $allocation->shop?->label() }}</td>
                    <td class="nowrap">{{ $allocation->allocation_date?->toFormattedDateString() }}</td>
                    <td class="nowrap">{{ $allocation->expiry_date?->toFormattedDateString() ?? '—' }}</td>
                    <td>@include('partials.status-badge', ['status' => $allocation->status])</td>
                    <td class="right">
                        @if ($allocation->documents()->where('type', 'letter')->exists())
                            <a href="{{ route('documents.letter', $allocation) }}" class="btn sm secondary">Letter</a>
                        @endif
                        @if ($allocation->activeCard)
                            <a href="{{ route('documents.card', $allocation) }}" class="btn sm secondary">Card</a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">No allocations on record.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div></div>
@endsection
