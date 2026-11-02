<label>Procedure</label><input type="text" name="procedure" value="{{ old('procedure') }}" required data-autofocus placeholder="e.g. Chest drain insertion, Laparotomy">
@include('partials.field-error', ['name' => 'procedure'])
<div class="two">
    <div><label>Urgency</label><select name="urgency">@foreach (config('ward.urgencies') as $k => $l)<option value="{{ $k }}" @selected(old('urgency') === $k)>{{ $l }}</option>@endforeach</select></div>
    <div><label>&nbsp;</label><label class="inline"><input type="checkbox" name="consent" value="1" @checked(old('consent'))> Consent obtained</label></div>
</div>
<label>Indication</label><textarea name="indication" required>{{ old('indication') }}</textarea>
@include('partials.field-error', ['name' => 'indication'])
<div data-types="theatre">
    <div class="two">
        <div><label>Theatre date</label><input type="date" name="theatre_date" min="{{ now()->toDateString() }}" value="{{ old('theatre_date') }}">@include('partials.field-error', ['name' => 'theatre_date'])</div>
        <div><label>Anaesthetic</label><select name="anaesthetic">@foreach (['general' => 'General', 'regional' => 'Regional', 'local' => 'Local', 'sedation' => 'Sedation'] as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
        <div><label>Surgeon</label><input type="text" name="surgeon" value="{{ old('surgeon') }}"></div>
        <div><label>Nil by mouth from</label><input type="time" name="fasting_from" value="{{ old('fasting_from') }}"><small>Creates a nursing task.</small></div>
    </div>
</div>
