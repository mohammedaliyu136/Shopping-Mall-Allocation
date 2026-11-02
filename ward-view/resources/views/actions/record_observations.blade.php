<fieldset><legend>Vital signs (NEWS2 is calculated for you)</legend>
    <div class="three">
        <div><label>Resp. rate /min</label><input type="number" name="respiratory_rate" value="{{ old('respiratory_rate') }}" required data-autofocus>@include('partials.field-error', ['name' => 'respiratory_rate'])</div>
        <div><label>SpO₂ %</label><input type="number" name="spo2" value="{{ old('spo2') }}" required>@include('partials.field-error', ['name' => 'spo2'])</div>
        <div><label>Air or oxygen?</label><select name="on_oxygen"><option value="0">Room air</option><option value="1" @selected(old('on_oxygen') === '1')>On oxygen</option></select></div>
        <div data-show-when="on_oxygen=1"><label>O₂ flow / device</label><input type="text" name="oxygen_flow" value="{{ old('oxygen_flow') }}" placeholder="e.g. 2 L nasal"></div>
        <div><label>Systolic BP</label><input type="number" name="systolic_bp" value="{{ old('systolic_bp') }}" required>@include('partials.field-error', ['name' => 'systolic_bp'])</div>
        <div><label>Diastolic BP</label><input type="number" name="diastolic_bp" value="{{ old('diastolic_bp') }}"></div>
        <div><label>Heart rate</label><input type="number" name="heart_rate" value="{{ old('heart_rate') }}" required>@include('partials.field-error', ['name' => 'heart_rate'])</div>
        <div><label>Temperature °C</label><input type="number" step="0.1" name="temperature" value="{{ old('temperature') }}" required>@include('partials.field-error', ['name' => 'temperature'])</div>
        <div><label>Consciousness</label><select name="consciousness">@foreach (config('ward.consciousness') as $k => $l)<option value="{{ $k }}" @selected(old('consciousness') === $k)>{{ $k }} — {{ $l }}</option>@endforeach</select></div>
    </div>
</fieldset>
<fieldset><legend>Pain, glucose &amp; fluid balance</legend>
    <div class="three">
        <div><label>Pain score (0–10)</label><input type="number" name="pain_score" min="0" max="10" value="{{ old('pain_score') }}"></div>
        <div><label>Blood glucose mmol/L</label><input type="number" step="0.1" name="blood_glucose" value="{{ old('blood_glucose') }}"></div>
        <div></div>
        <div><label>Input since last (ml)</label><input type="number" name="fluid_in_ml" value="{{ old('fluid_in_ml') }}"></div>
        <div><label>Output since last (ml)</label><input type="number" name="fluid_out_ml" value="{{ old('fluid_out_ml') }}"></div>
        <div><label>Output type</label><select name="output_route"><option value="">—</option>@foreach (['urine' => 'Urine', 'drain' => 'Drain', 'vomit' => 'Vomit', 'stool' => 'Stool', 'ng' => 'NG aspirate'] as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
    </div>
</fieldset>
@if ($critical)
    <fieldset><legend>Ventilator</legend>
        <div class="three">
            <div><label>Mode</label><select name="vent_mode">@foreach (config('ward.ventilation_modes') as $k => $l)<option value="{{ $k }}" @selected(old('vent_mode', $lastVent['mode_key'] ?? 'none') === $k)>{{ $l }}</option>@endforeach</select></div>
            <div><label>FiO₂ %</label><input type="number" name="fio2" value="{{ old('fio2', $lastVent['fio2'] ?? '') }}"></div>
            <div><label>PEEP cmH₂O</label><input type="number" step="0.5" name="peep" value="{{ old('peep', $lastVent['peep'] ?? '') }}"></div>
            <div><label>Tidal volume ml</label><input type="number" name="tidal_volume" value="{{ old('tidal_volume', $lastVent['tidal_volume'] ?? '') }}"></div>
            <div><label>Set rate /min</label><input type="number" name="set_rate" value="{{ old('set_rate', $lastVent['set_rate'] ?? '') }}"></div>
        </div>
        <small>Previous settings are pre-filled; change only what has changed.</small>
    </fieldset>
    <fieldset><legend>Infusions running</legend>
        @forelse ($infusions as $inf)
            <div class="two"><label style="margin-top:14px">{{ $inf->summary }}</label>
                <div><input type="text" name="infusion_rates[{{ $inf->id }}]" placeholder="Current rate, e.g. 8 ml/hr"></div></div>
        @empty
            <p class="muted">No infusions prescribed.</p>
        @endforelse
    </fieldset>
@endif
