<div class="stats">
    <div class="stat"><small>In department</small><b>{{ $encounters->count() }}</b></div>
    <div class="stat"><small>Waiting to be seen</small><b>{{ $stats['waiting'] }}</b></div>
    <div class="stat {{ $stats['breaching'] ? 'bad' : '' }}"><small>Past triage target</small><b>{{ $stats['breaching'] }}</b></div>
    <div class="stat {{ $stats['longest'] > 240 ? 'bad' : '' }}"><small>Longest wait</small><b>{{ intdiv($stats['longest'], 60) }}h {{ $stats['longest'] % 60 }}m</b></div>
    <div class="stat"><small>Admitted, awaiting bed</small><b>{{ $stats['awaiting_bed'] }}</b></div>
</div>
<div class="panel" style="padding:0">
    <table>
        <thead><tr><th>Triage</th><th>Patient</th><th>Complaint</th><th>Arrived</th><th>Waiting</th><th>Seen</th><th>Disposition</th><th></th></tr></thead>
        <tbody>
        @forelse ($encounters as $e)
            @php($t = $e->triage_category)
            <tr class="{{ $e->isBreachingTarget() ? 'breach' : '' }}">
                <td><span class="triage {{ $t?->colour() ?? 'none' }}"></span>{{ $t?->label() ?? 'Not triaged' }}</td>
                <td><strong>{{ $e->patient->name }}</strong><br><small>{{ $e->patient->age() }}{{ strtoupper(substr($e->patient->sex, 0, 1)) }} · {{ $e->patient->mrn }}</small>
                    @if ($e->open_alerts_count)<br><span class="pill red">🚨 Alert</span>@endif
                    @if ($e->patient->hasAllergies())<span class="pill red">Allergy</span>@endif
                </td>
                <td>{{ $e->presenting_complaint }}</td>
                <td>{{ $e->arrived_at->format('H:i') }}</td>
                <td>
                    {{ intdiv($e->waitingMinutes(), 60) }}h {{ $e->waitingMinutes() % 60 }}m
                    @if ($e->isBreachingTarget())<br><span class="pill red">over {{ $t->targetMinutes() }}m target</span>@endif
                </td>
                <td>{{ $e->seen_at?->format('H:i') ?? '—' }}</td>
                <td>
                    @if ($e->pendingTransfer)<span class="pill blue">Admit → {{ $e->pendingTransfer->toWard->name }}</span>
                    @else <span class="muted">—</span> @endif
                </td>
                <td><a class="btn sm" href="{{ route('encounters.show', $e) }}">Open</a></td>
            </tr>
        @empty
            <tr><td colspan="8" class="muted" style="text-align:center;padding:30px">No patients in the department.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
