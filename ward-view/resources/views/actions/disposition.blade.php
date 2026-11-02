@if ($pending)
    <p class="hint warn">Admission to {{ $pending->toWard?->name }} already requested.</p>
@endif
<label>Working diagnosis</label><input type="text" name="diagnosis" value="{{ old('diagnosis', $encounter->diagnoses[0] ?? '') }}" required>
@include('partials.field-error', ['name' => 'diagnosis'])
<div data-types="admit">
    <div class="two">
        <div><label>Admit to ward</label><select name="to_ward_id">@foreach ($wards as $w)<option value="{{ $w->id }}">{{ $w->name }} ({{ $w->type()?->label() }})</option>@endforeach</select></div>
        <div><label>Under team</label><select name="team_id">@foreach ($teams as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach</select></div>
    </div>
    <p class="hint">The patient stays on the A&amp;E board until a nurse on the receiving ward accepts them.</p>
</div>
<div data-types="refer">
    <label>Refer to</label><input type="text" name="refer_to" value="{{ old('refer_to') }}" placeholder="e.g. Fracture clinic, General Hospital Kano">
    @include('partials.field-error', ['name' => 'refer_to'])
</div>
<label>Notes</label><textarea name="notes">{{ old('notes') }}</textarea>
