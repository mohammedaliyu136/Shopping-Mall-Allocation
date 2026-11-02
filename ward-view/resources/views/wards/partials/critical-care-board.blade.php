@include('wards.partials.incoming')
@php($list = $mineOnly ? $encounters->where('assigned_nurse_id', auth()->id()) : $encounters)
<div class="stats">
    <div class="stat"><small>Patients</small><b>{{ $encounters->count() }}</b></div>
    <div class="stat"><small>Free beds</small><b>{{ $beds->count() - $byBed->count() }}</b></div>
    <div class="stat"><small>Ventilated</small><b>{{ $encounters->filter(fn ($e) => ($m = $e->latestObservation?->data('ventilator.mode_key')) && $m !== 'none')->count() }}</b></div>
    <div class="stat {{ $encounters->whereNull('assigned_nurse_id')->count() ? 'bad' : '' }}"><small>Without a 1:1 nurse</small><b>{{ $encounters->whereNull('assigned_nurse_id')->count() }}</b></div>
</div>
@if ($mineOnly)
    <p class="hint">1:1 nursing view: showing only the patients assigned to you. <a href="{{ route('wards.show', $ward) }}">Show the whole unit</a></p>
@endif
<div class="beds">
    @foreach ($beds as $bed)
        @php($e = $byBed->get($bed->id))
        @continue($mineOnly && (! $e || $e->assigned_nurse_id !== auth()->id()))
        @if ($e)
            @php($obs = $e->latestObservation)
            <div class="bed {{ $e->open_alerts_count ? 'alert' : '' }}">
                <div class="row"><span class="label grow">{{ $bed->group_label }} · {{ $bed->label }}</span>
                    @if ($obs)<span class="pill {{ ['high' => 'red', 'medium' => 'red', 'low-medium' => 'amber'][$obs->status] ?? '' }}">NEWS2 {{ $obs->score }}</span>@endif
                </div>
                <a class="name" href="{{ route('encounters.show', $e) }}">{{ $e->patient->name }}</a>
                <div><small>{{ $e->patient->age() }}{{ strtoupper(substr($e->patient->sex, 0, 1)) }} · {{ $e->diagnoses[0] ?? $e->team?->name }}</small></div>
                @if ($obs)
                    <div><small>
                        HR {{ $obs->data('heart_rate') }} · BP {{ $obs->data('systolic_bp') }}/{{ $obs->data('diastolic_bp') }} · SpO₂ {{ $obs->data('spo2') }}%
                        @if ($v = $obs->data('ventilator')) <br>🫁 {{ $v['mode'] }}{{ isset($v['fio2']) ? ', FiO₂ '.$v['fio2'].'%' : '' }}{{ isset($v['peep']) ? ', PEEP '.$v['peep'] : '' }} @endif
                        <br>Last obs {{ $obs->recorded_at->format('H:i') }}
                        @if ($e->obsDueAt($config)?->isPast()) <span class="pill red">hourly obs overdue</span> @endif
                    </small></div>
                @else
                    <div class="flags"><span class="pill amber">No obs yet</span></div>
                @endif
                <form method="POST" action="{{ route('wards.assign-nurse', [$ward, $e]) }}" class="row" style="margin-top:8px;gap:6px">
                    @csrf
                    <select name="nurse_id" aria-label="1:1 nurse" style="flex:1;padding:4px" @disabled(! auth()->user()->isClinical()) onchange="this.form.submit()">
                        <option value="">— No 1:1 nurse —</option>
                        @foreach ($nurses as $n)<option value="{{ $n->id }}" @selected($e->assigned_nurse_id === $n->id)>1:1 {{ $n->name }}</option>@endforeach
                    </select>
                </form>
                <div class="flags">
                    @if ($e->open_alerts_count)<span class="pill red">🚨 Alert</span>@endif
                    @if ($e->patient->dnr_status === \App\Enums\DnrStatus::Dnacpr)<span class="pill" style="background:var(--purple-soft);color:var(--purple)">DNACPR</span>@endif
                    @if ($e->patient->hasAllergies())<span class="pill red">Allergy</span>@endif
                    @if ($e->pending_samples_count)<span class="pill blue">{{ $e->pending_samples_count }} sample(s)</span>@endif
                    @if ($e->pendingTransfer)<span class="pill blue">→ {{ $e->pendingTransfer->toWard->name }}</span>@endif
                    @if ($e->isDischargePending())<span class="pill green">Step-down / discharge</span>@endif
                </div>
            </div>
        @else
            <div class="bed empty"><span class="label">{{ $bed->group_label }} · {{ $bed->label }}</span><div>Empty</div></div>
        @endif
    @endforeach
</div>
