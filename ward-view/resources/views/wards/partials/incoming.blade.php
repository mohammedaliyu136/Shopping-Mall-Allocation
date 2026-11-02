@if ($incoming->isNotEmpty())
    <div class="panel incoming">
        <h2>Incoming patients ({{ $incoming->count() }})</h2>
        <p class="muted">Accept with <strong>Move Patient → Accept transfer</strong> on the patient's page.</p>
        <table>
            <tr><th>Patient</th><th>From</th><th>Type</th><th>Team</th><th>Reason</th><th>Requested</th><th></th></tr>
            @foreach ($incoming as $t)
                <tr>
                    <td><strong>{{ $t->encounter->patient->name }}</strong><br><small>{{ $t->encounter->patient->mrn }}</small></td>
                    <td>{{ $t->fromWard->name }}</td>
                    <td>{{ ['ward' => 'Ward transfer', 'escalation' => 'Escalation', 'admission' => 'Admission from A&E'][$t->type] ?? $t->type }}</td>
                    <td>{{ $t->toTeam?->name ?? '—' }}</td>
                    <td>{{ $t->reason }}</td>
                    <td><small>{{ $t->created_at->diffForHumans() }}<br>by {{ $t->requester->name }}</small></td>
                    <td><a class="btn sm" href="{{ route('encounters.show', $t->encounter) }}">Open</a></td>
                </tr>
            @endforeach
        </table>
    </div>
@endif
