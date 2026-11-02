<p class="hint">Saving starts the discharge. Nurses then complete the discharge checklist, which frees the bed.</p>
<div data-types="home ama facility">
    <label>Discharge summary</label><textarea name="summary" data-autofocus>{{ old('summary') }}</textarea>
    @include('partials.field-error', ['name' => 'summary'])
    <label>Discharge medication</label><textarea name="discharge_medication">{{ old('discharge_medication') }}</textarea>
    <label>Follow-up</label><input type="text" name="follow_up" value="{{ old('follow_up') }}" placeholder="e.g. Medical clinic in 2 weeks">
</div>
<div data-types="facility">
    <label>Receiving facility</label><input type="text" name="facility" value="{{ old('facility') }}">
    @include('partials.field-error', ['name' => 'facility'])
</div>
<div data-types="ama">
    <label class="inline"><input type="checkbox" name="risks_explained" value="1"> Risks of leaving have been explained and documented</label>
    @include('partials.field-error', ['name' => 'risks_explained'])
</div>
<div data-types="deceased">
    <div class="two">
        <div><label>Time of death</label><input type="datetime-local" name="time_of_death" max="{{ now()->format('Y-m-d\TH:i') }}" value="{{ old('time_of_death') }}">@include('partials.field-error', ['name' => 'time_of_death'])</div>
        <div><label>Death verified by</label><input type="text" name="verified_by" value="{{ old('verified_by', auth()->user()->name) }}"></div>
    </div>
    <label>Cause of death</label><textarea name="cause_of_death">{{ old('cause_of_death') }}</textarea>
    @include('partials.field-error', ['name' => 'cause_of_death'])
</div>
