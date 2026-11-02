<div data-types="bed_change">
    <label>Move to bed</label>
    @if ($emptyBeds->isEmpty())
        <p class="hint warn">No empty beds on this ward.</p>
    @else
        <select name="bed_id">@foreach ($emptyBeds as $b)<option value="{{ $b->id }}">{{ $b->label }} ({{ $b->group_label }})</option>@endforeach</select>
    @endif
</div>
<div data-types="accept_transfer">
    @if ($transfer)
        <p class="hint">From <strong>{{ $transfer->fromWard->name }}</strong>{{ $transfer->toTeam ? ' under '.$transfer->toTeam->name : '' }}. Reason: {{ $transfer->reason ?? '—' }}</p>
        <label>Bed on {{ $transfer->toWard->name }}</label>
        @if ($receivingBeds->isEmpty())
            <p class="hint warn">No empty beds on {{ $transfer->toWard->name }}.</p>
        @else
            <select name="bed_id">@foreach ($receivingBeds as $b)<option value="{{ $b->id }}">{{ $b->label }} ({{ $b->group_label }})</option>@endforeach</select>
        @endif
    @endif
</div>
@include('partials.field-error', ['name' => 'bed_id'])
<div data-types="off_ward">
    <div class="two">
        <div><label>Going to</label><select name="destination">@foreach (config('ward.off_ward_destinations') as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
        <div><label>Escort</label><input type="text" name="escort" value="{{ old('escort') }}" placeholder="e.g. Porter + RN"></div>
    </div>
</div>
<div data-types="returned">
    <p class="hint">Patient is currently off the ward: {{ $encounter->off_ward }}.</p>
</div>
<label>Notes</label><textarea name="notes">{{ old('notes') }}</textarea>
