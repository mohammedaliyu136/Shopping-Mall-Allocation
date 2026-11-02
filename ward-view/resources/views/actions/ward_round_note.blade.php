<fieldset><legend>Progress</legend>
    <textarea name="progress" required data-autofocus placeholder="Subjective, examination, results reviewed">{{ old('progress') }}</textarea>
    @include('partials.field-error', ['name' => 'progress'])
    <label>Plan</label><textarea name="plan">{{ old('plan') }}</textarea>
</fieldset>
<fieldset><legend>Diagnosis &amp; problem list</legend>
    <textarea name="diagnoses" placeholder="One problem per line">{{ old('diagnoses', implode("\n", $encounter->diagnoses ?? [])) }}</textarea>
    <small>Edits here replace the problem list shown in the patient header.</small>
</fieldset>
<fieldset><legend>Nursing instructions</legend>
    <textarea name="nursing_instructions" placeholder="Becomes a task on the nurses' list">{{ old('nursing_instructions') }}</textarea>
</fieldset>
<div class="two">
    <div><label>Diet</label>
        <select name="diet"><option value="">No change</option>@foreach (config('ward.diets') as $k => $l)<option value="{{ $k }}" @selected(old('diet') === $k)>{{ $l }}</option>@endforeach</select></div>
    <div><label>Observation frequency</label>
        <select name="obs_frequency_hours"><option value="">No change ({{ config('ward.obs_frequencies')[$encounter->obs_frequency_hours ?? 0] ?? 'ward default' }})</option>@foreach (config('ward.obs_frequencies') as $k => $l)<option value="{{ $k }}" @selected((string) old('obs_frequency_hours') === (string) $k)>{{ $l }}</option>@endforeach</select></div>
</div>
