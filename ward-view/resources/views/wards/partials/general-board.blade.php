@include('wards.partials.incoming')
@php($occupied = $byBed->count())
<div class="stats">
    <div class="stat"><small>Patients</small><b>{{ $encounters->count() }}</b></div>
    <div class="stat"><small>Free beds</small><b>{{ $beds->count() - $occupied }}</b></div>
    <div class="stat {{ $encounters->where('open_alerts_count', '>', 0)->count() ? 'bad' : '' }}"><small>Open alerts</small><b>{{ $encounters->where('open_alerts_count', '>', 0)->count() }}</b></div>
    <div class="stat"><small>Discharges in progress</small><b>{{ $encounters->filter->isDischargePending()->count() }}</b></div>
</div>
<div class="bed-groups">
    @foreach ($beds->groupBy('group_label') as $group => $groupBeds)
        <section class="bed-group">
            <h3>{{ $group }}</h3>
            <div class="beds">
                @foreach ($groupBeds as $bed)
                    @if ($e = $byBed->get($bed->id))
                        @include('wards.partials.bed-card', ['e' => $e])
                    @else
                        <div class="bed empty"><span class="label">{{ $bed->label }}</span><div>Empty</div></div>
                    @endif
                @endforeach
            </div>
        </section>
    @endforeach
    @if ($unbedded->isNotEmpty())
        <section class="bed-group"><h3>Awaiting a bed</h3><div class="beds">
            @foreach ($unbedded as $e) @include('wards.partials.bed-card', ['e' => $e]) @endforeach
        </div></section>
    @endif
</div>
