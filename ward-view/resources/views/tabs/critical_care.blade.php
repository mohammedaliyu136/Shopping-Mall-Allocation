<h3>Ventilation (last 24 h)</h3>
@if ($ventilation->isEmpty())
    <p class="muted">No ventilator settings recorded.</p>
@else
<div class="chart"><table>
    <tr><th></th>@foreach ($ventilation as $o)<th>{{ $o->recorded_at->format('H:i') }}</th>@endforeach</tr>
    @foreach (['mode' => 'Mode', 'fio2' => 'FiO₂ %', 'peep' => 'PEEP', 'tidal_volume' => 'Vt ml', 'set_rate' => 'Set rate'] as $k => $l)
        <tr><td>{{ $l }}</td>@foreach ($ventilation as $o)<td>{{ $o->data("ventilator.$k") ?? '–' }}</td>@endforeach</tr>
    @endforeach
    <tr><td>SpO₂ %</td>@foreach ($ventilation as $o)<td>{{ $o->data('spo2') }}</td>@endforeach</tr>
</table></div>
@endif

<h3 style="margin-top:16px">Infusions</h3>
@if ($infusions->isEmpty())
    <p class="muted">No infusions running.</p>
@else
<table>
    <tr><th>Infusion</th><th>Prescribed</th><th>Rates recorded (latest first)</th></tr>
    @foreach ($infusions as $inf)
        <tr>
            <td><strong>{{ $inf->summary }}</strong></td>
            <td><small>{{ $inf->recorded_at->format('j M H:i') }}</small></td>
            <td>
                @forelse (($rates[$inf->id] ?? collect())->sortByDesc('at')->take(8) as $r)
                    <span class="pill blue">{{ $r['at']->format('H:i') }} · {{ $r['rate'] }}</span>
                @empty
                    <small class="muted">No rate recorded yet — record it with the hourly observations.</small>
                @endforelse
            </td>
        </tr>
    @endforeach
</table>
@endif
