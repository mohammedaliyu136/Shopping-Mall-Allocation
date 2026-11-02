@php($rows = [
    'respiratory_rate' => 'Resp. rate', 'spo2' => 'SpO₂ %', 'on_oxygen' => 'Air / O₂', 'systolic_bp' => 'BP',
    'heart_rate' => 'Heart rate', 'consciousness' => 'ACVPU', 'temperature' => 'Temp °C',
])
<div class="row" style="margin-bottom:10px">
    <div class="grow"><strong>{{ $chartType->label() }}</strong> · obs {{ strtolower(config('ward.obs_frequencies')[$frequency] ?? $frequency.'-hourly') }}
        @if ($dueAt) · next due <span class="{{ $dueAt->isPast() ? 'pill red' : '' }}">{{ $dueAt->format('H:i') }}</span>@endif
    </div>
    <span class="pill blue">Fluid balance today: in {{ $fluidIn }} ml · out {{ $fluidOut }} ml · {{ $fluidIn - $fluidOut >= 0 ? '+' : '' }}{{ $fluidIn - $fluidOut }} ml</span>
</div>
@if ($obs->isEmpty())
    <p class="muted">No observations recorded in this period.</p>
@else
<div class="chart">
<table>
    <tr><th></th>@foreach ($obs as $o)<th>{{ $o->recorded_at->format($chartType === \App\Enums\ObsChartType::Hourly ? 'H:i' : 'j/n H:i') }}</th>@endforeach</tr>
    @foreach ($rows as $key => $label)
        <tr><td>{{ $label }}</td>
            @foreach ($obs as $o)
                @php($s = $o->data("news_parts.$key"))
                <td class="{{ $s ? 's'.$s : '' }}">
                    @switch($key)
                        @case('on_oxygen') {{ $o->data('on_oxygen') ? 'O₂' : 'Air' }} @break
                        @case('systolic_bp') {{ $o->data('systolic_bp') }}{{ $o->data('diastolic_bp') ? '/'.$o->data('diastolic_bp') : '' }} @break
                        @default {{ $o->data($key) }}
                    @endswitch
                </td>
            @endforeach
        </tr>
    @endforeach
    <tr><td><strong>NEWS2</strong></td>@foreach ($obs as $o)<td class="{{ ['high' => 's3', 'medium' => 's2', 'low-medium' => 's1'][$o->status] ?? '' }}"><strong>{{ $o->score }}</strong></td>@endforeach</tr>
    <tr><td>Pain</td>@foreach ($obs as $o)<td>{{ $o->data('pain_score') ?? '' }}</td>@endforeach</tr>
    <tr><td>In / out ml</td>@foreach ($obs as $o)<td><small>{{ $o->data('fluid_in_ml') ?? '–' }} / {{ $o->data('fluid_out_ml') ?? '–' }}</small></td>@endforeach</tr>
    <tr><td>By</td>@foreach ($obs as $o)<td><small class="muted">{{ \Illuminate\Support\Str::of($o->author?->name)->explode(' ')->last() }}</small></td>@endforeach</tr>
</table>
</div>
<p class="muted"><small>Shaded cells scored on NEWS2: <span class="pill" style="background:#fff8d6">1</span> <span class="pill" style="background:#ffe1b8">2</span> <span class="pill" style="background:#ffc6c0">3</span></small></p>
@endif
