@extends('layouts.app')
@section('title', 'Request a transfer')

@section('content')
    <div class="page-head"><h1>Request an ownership transfer</h1></div>

    @if (\App\Support\Money::isPositive($outstanding))
        <div class="alert bad">
            You currently owe @include('partials.money', ['amount' => $outstanding]).
            Mall management cannot approve a transfer until all outstanding rent, service charges and fees are settled.
        </div>
    @endif

    <div class="card" style="max-width:620px"><div class="card-body">
        @if ($allocations->isEmpty())
            <p class="alert info mb0">You have no active allocation that can be transferred.</p>
        @else
            <form method="POST" action="{{ route('portal.transfers.store') }}">
                @csrf
                <div class="field"><label for="allocation_id">Shop to transfer</label>
                    <select id="allocation_id" name="allocation_id" required>
                        <option value="">Select…</option>
                        @foreach ($allocations as $allocation)
                            <option value="{{ $allocation->id }}" @selected(old('allocation_id') == $allocation->id)>
                                {{ $allocation->shop?->label() }} ({{ $allocation->reference_number }})
                            </option>
                        @endforeach
                    </select></div>

                <div class="field"><label for="to_owner_email">Proposed new owner's email</label>
                    <input id="to_owner_email" type="email" name="to_owner_email" value="{{ old('to_owner_email') }}" required>
                    <div class="hint">The new owner must already be registered with mall management.</div></div>

                <div class="field"><label for="reason">Reason for the transfer</label>
                    <textarea id="reason" name="reason" required>{{ old('reason') }}</textarea></div>

                <div class="actions">
                    <button type="submit" class="btn">Submit request</button>
                    <a href="{{ route('portal.transfers.index') }}" class="btn secondary">Cancel</a>
                </div>
            </form>
        @endif
    </div></div>
@endsection
