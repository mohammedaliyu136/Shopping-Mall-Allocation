<label>Note</label>
<textarea name="note" required data-autofocus style="min-height:110px">{{ old('note') }}</textarea>
@include('partials.field-error', ['name' => 'note'])
<div data-types="care">
    <label>Care given</label>
    <div class="checks">
        @foreach (['hygiene' => 'Personal hygiene', 'mouth_care' => 'Mouth care', 'repositioned' => 'Repositioned', 'mobilised' => 'Mobilised', 'fed' => 'Assisted with meals', 'continence' => 'Continence care', 'catheter_care' => 'Catheter care', 'line_care' => 'Line / cannula care'] as $k => $l)
            <label><input type="checkbox" name="care_items[]" value="{{ $k }}"> {{ $l }}</label>
        @endforeach
    </div>
</div>
<div data-types="wound">
    <div class="two">
        <div><label>Wound site</label><input type="text" name="wound_site" value="{{ old('wound_site') }}">@include('partials.field-error', ['name' => 'wound_site'])</div>
        <div><label>Appearance</label><select name="wound_appearance">@foreach (['clean' => 'Clean', 'granulating' => 'Granulating', 'sloughy' => 'Sloughy', 'necrotic' => 'Necrotic', 'infected' => 'Signs of infection'] as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
        <div><label>Dressing applied</label><input type="text" name="dressing" value="{{ old('dressing') }}"></div>
        <div><label>Next dressing due</label><input type="datetime-local" name="next_dressing" value="{{ old('next_dressing') }}"><small>Adds a nursing task.</small></div>
    </div>
</div>
<div data-types="concern">
    <p class="hint">If the patient is deteriorating, use <strong>Alert Doctor</strong> instead so a doctor is notified.</p>
</div>
