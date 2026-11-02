@php($p = $e->patient)
@php($obs = $e->latestObservation)
@php($due = $e->obsDueAt($config))
<a class="bed {{ $e->open_alerts_count || ($obs && in_array($obs->status, ['high', 'medium'])) ? 'alert' : '' }}" href="{{ route('encounters.show', $e) }}">
    <div class="row"><span class="label grow">{{ $e->bed?->label ?? 'No bed' }}</span>
        @if ($obs)<span class="pill {{ ['high' => 'red', 'medium' => 'red', 'low-medium' => 'amber'][$obs->status] ?? '' }}">NEWS2 {{ $obs->score }}</span>@endif
    </div>
    <div class="name">{{ $p->name }}</div>
    <small>{{ $p->age() }}{{ strtoupper(substr($p->sex, 0, 1)) }} · {{ $e->team?->name ?? 'No team' }}</small>
    @if ($e->diagnoses)<div><small>{{ \Illuminate\Support\Str::limit(implode(', ', $e->diagnoses), 60) }}</small></div>@endif
    <div class="flags">
        @if ($e->open_alerts_count)<span class="pill red">🚨 Alert</span>@endif
        @if ($p->critical_flag)<span class="pill red">Critical</span>@endif
        @if ($p->dnr_status === \App\Enums\DnrStatus::Dnacpr)<span class="pill" style="background:var(--purple-soft);color:var(--purple)">DNACPR</span>@endif
        @if ($p->hasAllergies())<span class="pill red">Allergy</span>@endif
        @if (! $obs || $due?->isPast())<span class="pill amber">Obs {{ $obs ? 'overdue' : 'due' }}</span>@endif
        @if ($e->pending_samples_count)<span class="pill blue">{{ $e->pending_samples_count }} sample{{ $e->pending_samples_count > 1 ? 's' : '' }}</span>@endif
        @if ($e->off_ward)<span class="pill blue">Off ward: {{ $e->off_ward }}</span>@endif
        @if ($e->pendingTransfer)<span class="pill blue">→ {{ $e->pendingTransfer->toWard->name }}</span>@endif
        @if ($e->isDischargePending())<span class="pill green">Discharging</span>@endif
    </div>
</a>
