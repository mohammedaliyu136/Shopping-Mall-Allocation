<label>Requested tests waiting for a sample</label>
<div class="checks">
    @foreach ($waiting as $req)
        <label><input type="checkbox" name="request_ids[]" value="{{ $req->id }}" checked>
            <span>{{ $req->data('item') }} <small class="muted">{{ config('ward.urgencies')[$req->data('urgency')] ?? '' }} · {{ $req->author->name }}, {{ $req->recorded_at->format('H:i j M') }}</small></span></label>
    @endforeach
</div>
@include('partials.field-error', ['name' => 'request_ids'])
<div class="two">
    <div><label>Time taken</label><input type="datetime-local" name="collected_at" value="{{ old('collected_at', now()->format('Y-m-d\TH:i')) }}" max="{{ now()->format('Y-m-d\TH:i') }}"></div>
    <div><label>Site</label><input type="text" name="site" value="{{ old('site') }}" placeholder="e.g. Left antecubital"></div>
</div>
<label>Notes</label><textarea name="notes">{{ old('notes') }}</textarea>
