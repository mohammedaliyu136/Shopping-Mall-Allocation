@php($items = $encounter->discharge_checklist ?? [])
@php($complete = collect($items)->every(fn ($i) => $i['done']))
<section class="panel" style="border-left:4px solid var(--green)">
    <h2>Discharge checklist</h2>
    <p class="muted">Started {{ $encounter->discharge_started_at?->diffForHumans() }} · {{ ['home' => 'Home', 'ama' => 'Against medical advice', 'facility' => 'Transfer to another facility', 'deceased' => 'Deceased'][$encounter->discharge_type] ?? $encounter->discharge_type }}</p>
    <form method="POST" action="{{ route('encounters.checklist', $encounter) }}" class="checklist">
        @csrf
        @foreach ($items as $key => $item)
            <label>
                <input type="checkbox" name="items[]" value="{{ $key }}" @checked($item['done'])>
                <span>{{ $item['label'] }} @if ($item['done'])<small class="muted">— {{ $item['by'] }}, {{ $item['at'] }}</small>@endif</span>
            </label>
        @endforeach
        <div class="row" style="margin-top:10px">
            <button class="btn secondary" type="submit">Save checklist</button>
            <button class="btn" type="submit" name="complete" value="1" @disabled(! $complete) title="{{ $complete ? '' : 'Tick every item first' }}">Complete discharge &amp; free bed</button>
        </div>
    </form>
</section>
