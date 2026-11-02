<div data-types="new">
    <div class="two">
        <div><label>Drug</label><input type="text" name="drug" value="{{ old('drug') }}" list="drug-list" autocomplete="off">@include('partials.field-error', ['name' => 'drug'])</div>
        <div><label>Dose</label><input type="text" name="dose" value="{{ old('dose') }}" placeholder="e.g. 1 g, 5 mg/hr">@include('partials.field-error', ['name' => 'dose'])</div>
        <div><label>Route</label><select name="route">@foreach (config('ward.routes') as $k => $l)<option value="{{ $k }}" @selected(old('route') === $k)>{{ $l }}</option>@endforeach</select></div>
        <div><label>Frequency</label><select name="frequency">@foreach (config('ward.frequencies') as $k => $l)<option value="{{ $k }}" @selected(old('frequency') === $k)>{{ $l }}</option>@endforeach</select></div>
        <div><label>Duration</label><input type="text" name="duration" value="{{ old('duration') }}" placeholder="e.g. 5 days"></div>
        <div><label>Indication</label><input type="text" name="indication" value="{{ old('indication') }}"></div>
    </div>
    @if ($encounter->patient->hasAllergies())
        <p class="hint warn">Allergies: {{ implode(', ', $encounter->patient->allergies) }}</p>
    @endif
    <datalist id="drug-list">@foreach (['Paracetamol', 'Ceftriaxone', 'Metronidazole', 'Amoxicillin', 'Enoxaparin', 'Omeprazole', 'Morphine', 'Furosemide', 'Insulin (sliding scale)', 'Noradrenaline', 'Propofol', 'Artesunate'] as $d)<option value="{{ $d }}">@endforeach</datalist>
</div>
<div data-types="stop_modify">
    @if ($activeMeds->isEmpty())
        <p class="hint">There is nothing active on the drug chart.</p>
    @else
        <label>Prescription</label>
        <select name="prescription_id">@foreach ($activeMeds as $m)<option value="{{ $m->id }}" @selected((string) old('prescription_id') === (string) $m->id)>{{ $m->summary }}</option>@endforeach</select>
        @include('partials.field-error', ['name' => 'prescription_id'])
        <label>Change</label>
        <label class="inline"><input type="radio" name="change" value="stop" @checked(old('change', 'stop') === 'stop')> Stop</label>
        <label class="inline"><input type="radio" name="change" value="modify" @checked(old('change') === 'modify')> Modify dose / frequency</label>
        <div class="two" data-show-when="change=modify">
            <div><label>New dose</label><input type="text" name="new_dose" value="{{ old('new_dose') }}">@include('partials.field-error', ['name' => 'new_dose'])</div>
            <div><label>New frequency</label><select name="new_frequency"><option value="">Unchanged</option>@foreach (config('ward.frequencies') as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
        </div>
        <label>Reason</label><input type="text" name="reason" value="{{ old('reason') }}">@include('partials.field-error', ['name' => 'reason'])
    @endif
</div>
<div data-types="iv_fluids">
    <div class="two">
        <div><label>Fluid</label><input type="text" name="fluid" list="fluid-list" value="{{ old('fluid') }}">@include('partials.field-error', ['name' => 'fluid'])</div>
        <div><label>Additives</label><input type="text" name="additives" value="{{ old('additives') }}" placeholder="e.g. 20 mmol KCl"></div>
        <div><label>Volume (ml)</label><input type="number" name="volume_ml" value="{{ old('volume_ml', 1000) }}">@include('partials.field-error', ['name' => 'volume_ml'])</div>
        <div><label>Rate (ml/hr)</label><input type="number" name="rate_ml_hr" value="{{ old('rate_ml_hr', 125) }}">@include('partials.field-error', ['name' => 'rate_ml_hr'])</div>
    </div>
    <datalist id="fluid-list">@foreach (config('ward.iv_fluids') as $f)<option value="{{ $f }}">@endforeach</datalist>
</div>
<div data-types="transfusion">
    <div class="two">
        <div><label>Product</label><select name="product">@foreach (config('ward.blood_products') as $k => $l)<option value="{{ $k }}" @selected(old('product') === $k)>{{ $l }}</option>@endforeach</select></div>
        <div><label>Units</label><input type="number" name="units" min="1" max="10" value="{{ old('units', 1) }}"></div>
        <div><label>Rate</label><input type="text" name="transfusion_rate" value="{{ old('transfusion_rate') }}" placeholder="e.g. over 3 hours"></div>
        <div><label>Indication</label><input type="text" name="transfusion_indication" value="{{ old('transfusion_indication') }}" placeholder="e.g. Hb 62 g/L, symptomatic">@include('partials.field-error', ['name' => 'transfusion_indication'])</div>
    </div>
    <p class="hint">Check a valid group &amp; save sample is in the lab before the first unit.</p>
</div>
