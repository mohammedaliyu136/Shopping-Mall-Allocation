<div data-types="lab">
    <label>Tests</label>
    <div class="checks">
        @foreach (config('ward.common_labs') as $t)
            <label><input type="checkbox" name="tests[]" value="{{ $t }}" @checked(in_array($t, old('tests', [])))> {{ $t }}</label>
        @endforeach
    </div>
    <label>Other test</label><input type="text" name="other_test" value="{{ old('other_test') }}">
    @include('partials.field-error', ['name' => 'tests'])
</div>
<div data-types="radiology">
    <label>Examination</label><input type="text" name="item" list="imaging-list" value="{{ old('item') }}">
    <datalist id="imaging-list">@foreach (config('ward.common_imaging') as $t)<option value="{{ $t }}">@endforeach</datalist>
    @include('partials.field-error', ['name' => 'item'])
</div>
<div data-types="physio_ot dietitian other">
    <label>Service / what is needed</label><input type="text" name="item" value="{{ old('item') }}" placeholder="e.g. Chest physio, mobility assessment, swallow assessment">
    @include('partials.field-error', ['name' => 'item'])
</div>
<label>Urgency</label>
<select name="urgency">@foreach (config('ward.urgencies') as $k => $l)<option value="{{ $k }}" @selected(old('urgency') === $k)>{{ $l }}</option>@endforeach</select>
<label>Clinical details</label>
<textarea name="clinical_details" required>{{ old('clinical_details') }}</textarea>
@include('partials.field-error', ['name' => 'clinical_details'])
