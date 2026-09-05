@extends('layouts.app')
@section('title', 'New transfer request')

@section('content')
    <div class="page-head"><div><h1>Initiate ownership transfer</h1>
        <p class="sub">The current owner's outstanding obligations are checked before approval (FR-121).</p></div></div>

    <div class="card" style="max-width:680px"><div class="card-body">
        @if ($allocations->isEmpty())
            <p class="alert info mb0">There are no active allocations available for transfer.</p>
        @else
            <form method="POST" action="{{ route('admin.transfers.store') }}">
                @csrf
                <div class="field"><label for="allocation_id">Allocation to transfer</label>
                    <select id="allocation_id" name="allocation_id" required>
                        <option value="">Select an allocation…</option>
                        @foreach ($allocations as $allocation)
                            <option value="{{ $allocation->id }}" @selected(old('allocation_id', $selectedAllocation) == $allocation->id)>
                                {{ $allocation->reference_number }} — {{ $allocation->shop?->label() }} ({{ $allocation->owner?->full_name }})
                            </option>
                        @endforeach
                    </select></div>

                <div class="field"><label for="to_owner_id">Proposed new owner</label>
                    <select id="to_owner_id" name="to_owner_id" required>
                        <option value="">Select an owner…</option>
                        @foreach ($owners as $owner)
                            <option value="{{ $owner->id }}" @selected(old('to_owner_id') == $owner->id)>
                                {{ $owner->full_name }} ({{ $owner->owner_code }})
                            </option>
                        @endforeach
                    </select>
                    <div class="hint">The new owner must already be registered. Register them first if necessary.</div></div>

                <div class="field"><label for="reason">Reason for transfer</label>
                    <textarea id="reason" name="reason">{{ old('reason') }}</textarea></div>

                <div class="actions">
                    <button type="submit" class="btn">Create transfer request</button>
                    <a href="{{ route('admin.transfers.index') }}" class="btn secondary">Cancel</a>
                </div>
            </form>
        @endif
    </div></div>
@endsection
