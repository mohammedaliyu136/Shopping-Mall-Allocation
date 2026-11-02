<h3>Active drug chart</h3>
@if ($active->isEmpty())
    <p class="muted">Nothing active.</p>
@else
<table>
    <tr><th>Medication</th><th>Prescribed</th><th>Indication</th><th>Administrations</th></tr>
    @foreach ($active as $m)
        <tr>
            <td><strong>{{ $m->summary }}</strong>
                <br><small>{{ ['new' => 'Medication', 'modify' => 'Medication (modified)', 'iv_fluids' => 'IV fluids', 'transfusion' => 'Blood transfusion'][$m->type] ?? $m->type }}{{ $m->data('duration') ? ' · '.$m->data('duration') : '' }}</small></td>
            <td><small>{{ $m->recorded_at->format('j M H:i') }}<br>{{ $m->author->name }}</small></td>
            <td>{{ $m->data('indication') ?? '—' }}</td>
            <td>
                @forelse ($m->children->take(5) as $a)
                    <div><span class="pill {{ ['given' => 'green', 'delayed' => 'amber', 'missed' => 'red'][$a->status] ?? '' }}">{{ $a->status }}</span> <small>{{ $a->recorded_at->format('H:i j M') }} · {{ $a->author->name }}{{ $a->data('reason') ? ' · '.$a->data('reason') : '' }}</small></div>
                @empty
                    <small class="muted">Not yet given</small>
                @endforelse
            </td>
        </tr>
    @endforeach
</table>
@endif
@if ($inactive->isNotEmpty())
    <h3 style="margin-top:16px">Stopped / superseded</h3>
    <table>
        @foreach ($inactive as $m)
            <tr><td class="muted"><s>{{ $m->summary }}</s></td><td><span class="pill">{{ $m->status }}</span></td><td><small>{{ $m->recorded_at->format('j M H:i') }} · {{ $m->author->name }}</small></td></tr>
        @endforeach
    </table>
@endif
