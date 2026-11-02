<label>From the drug chart</label>
<select name="prescription_id" required data-autofocus>
    @foreach ($chart as $rx)
        @php($last = $rx->children->first())
        <option value="{{ $rx->id }}" @selected((string) old('prescription_id') === (string) $rx->id)>
            {{ $rx->summary }}{{ $last ? ' — last '.$last->status.' '.$last->recorded_at->format('H:i') : ' — not yet given' }}
        </option>
    @endforeach
</select>
@include('partials.field-error', ['name' => 'prescription_id'])
@if ($encounter->patient->hasAllergies())
    <p class="hint warn">Allergies: {{ implode(', ', $encounter->patient->allergies) }}</p>
@endif
<div data-types="given">
    <div class="two">
        <div><label>Dose given</label><input type="text" name="dose_given" value="{{ old('dose_given') }}" placeholder="As prescribed"></div>
        <div><label>Time given</label><input type="datetime-local" name="given_at" value="{{ old('given_at', now()->format('Y-m-d\TH:i')) }}" max="{{ now()->format('Y-m-d\TH:i') }}">@include('partials.field-error', ['name' => 'given_at'])</div>
    </div>
    <label>Second checker (controlled drugs / IV)</label><input type="text" name="witness" value="{{ old('witness') }}">
</div>
<div data-types="delayed missed">
    <label>Reason</label>
    <select name="reason">@foreach (config('ward.administration_reasons') as $k => $l)<option value="{{ $k }}" @selected(old('reason') === $k)>{{ $l }}</option>@endforeach</select>
    @include('partials.field-error', ['name' => 'reason'])
</div>
<div data-types="delayed">
    <label>Delay until</label><input type="time" name="delay_until" value="{{ old('delay_until') }}">
    @include('partials.field-error', ['name' => 'delay_until'])
</div>
<label>Notes</label><textarea name="notes">{{ old('notes') }}</textarea>
