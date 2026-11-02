@if ($latestObs)
    <p class="hint {{ in_array($latestObs->status, ['high', 'medium']) ? 'warn' : '' }}">Latest NEWS2 {{ $latestObs->score }} at {{ $latestObs->recorded_at->format('H:i') }}: {{ \Illuminate\Support\Str::after($latestObs->summary, '— ') }}</p>
@endif
<label>Urgency</label>
<select name="urgency">@foreach (config('ward.urgencies') as $k => $l)<option value="{{ $k }}" @selected(old('urgency', 'urgent') === $k)>{{ $l }}</option>@endforeach</select>
<label>Situation — what is happening now?</label><textarea name="situation" required data-autofocus>{{ old('situation') }}</textarea>
@include('partials.field-error', ['name' => 'situation'])
<label>Background</label><textarea name="background">{{ old('background') }}</textarea>
<label>Assessment — what do you think the problem is?</label><textarea name="assessment">{{ old('assessment') }}</textarea>
<label>Recommendation — what do you need?</label><textarea name="recommendation" placeholder="e.g. Review within 30 minutes">{{ old('recommendation') }}</textarea>
