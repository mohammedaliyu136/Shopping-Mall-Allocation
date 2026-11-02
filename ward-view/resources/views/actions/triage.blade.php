<label>Triage category</label>
<div class="type-selector">
    @foreach (\App\Enums\TriageCategory::cases() as $c)
        <label><input type="radio" name="triage_category" value="{{ $c->value }}" @checked((int) old('triage_category', $encounter->triage_category?->value) === $c->value)><span><span class="triage {{ $c->colour() }}"></span>{{ $c->label() }}</span></label>
    @endforeach
</div>
@include('partials.field-error', ['name' => 'triage_category'])
<label>Presenting complaint</label><input type="text" name="presenting_complaint" value="{{ old('presenting_complaint', $encounter->presenting_complaint) }}" required>
<div class="two">
    <div><label>Pain score (0–10)</label><input type="number" name="pain_score" min="0" max="10" value="{{ old('pain_score') }}"></div>
</div>
<label>Notes</label><textarea name="notes">{{ old('notes') }}</textarea>
<small>Record vital signs with <strong>Record Observations</strong>.</small>
