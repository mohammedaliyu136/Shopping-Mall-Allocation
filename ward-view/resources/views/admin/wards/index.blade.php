@extends('layouts.app')
@section('title', 'Ward setup')
@section('content')
<div class="row" style="margin-bottom:12px">
    <div class="grow"><h1>Ward setup</h1><p class="muted" style="margin:0">Wards choose from fixed types and switch features on or off. No code changes needed.</p></div>
    <a class="btn" href="{{ route('admin.wards.create') }}">+ New ward</a>
</div>
<div class="panel" style="padding:0">
<table>
    <tr><th>Ward</th><th>Type</th><th>Beds</th><th>Obs chart</th><th>Buttons (doctor / nurse)</th><th>Tabs</th><th>Rollout</th><th>Version</th><th></th></tr>
    @foreach ($wards as $w)
        @php($c = $w->config)
        <tr>
            <td><strong>{{ $w->name }}</strong><br><small class="muted">{{ $w->code }}{{ $w->is_active ? '' : ' · inactive' }} · {{ $w->active_encounters_count }} patients</small></td>
            @if ($c)
                <td>{{ $c->ward_type->label() }}</td>
                <td>{{ $c->bed_count }} <small class="muted">{{ $c->bed_layout->label() }}</small></td>
                <td>{{ $c->obs_chart_type->label() }}<br><small class="muted">{{ config('ward.obs_frequencies')[$c->obs_frequency_hours] ?? '' }}</small></td>
                <td>{{ count($c->doctor_actions) }} / {{ count($c->nurse_actions) }}</td>
                <td>{{ count($c->enabled_tabs) }}</td>
                <td><span class="pill {{ ['pilot' => 'blue', 'live' => 'green'][$c->rollout_stage->value] ?? '' }}">{{ $c->rollout_stage->label() }}</span></td>
                <td>v{{ $c->version }}</td>
            @else
                <td colspan="6" class="muted">No config yet — run <code>php artisan wards:map-types</code> or open to create.</td>
            @endif
            <td><a class="btn sm secondary" href="{{ route('admin.wards.edit', $w) }}">Configure</a></td>
        </tr>
    @endforeach
</table>
</div>
@endsection
