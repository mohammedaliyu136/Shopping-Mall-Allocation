@extends('layouts.app')
@section('title', 'Configure '.$ward->name)
@section('content')
<div class="row" style="margin-bottom:12px">
    <div class="grow"><h1>{{ $ward->name }}</h1><div class="muted">Configuration version {{ $config->version }} · <a href="{{ route('admin.wards.history', $ward) }}">Change log</a></div></div>
    <a class="btn secondary" href="{{ route('wards.show', $ward) }}">View ward</a>
</div>
<form method="POST" action="{{ route('admin.wards.update', $ward) }}" data-ward-setup data-catalogue='@json($catalogue)'>
    @csrf @method('PUT')
    <div class="grid cols-2">
        <section class="panel">
            <h2>Ward</h2>
            <label>Name</label><input type="text" name="name" value="{{ old('name', $ward->name) }}" required>
            <label>Specialty</label><input type="text" name="specialty" value="{{ old('specialty', $ward->specialty) }}">
            <label class="inline"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $ward->is_active))> Active</label>

            <label>Ward type</label>
            <select name="ward_type">@foreach (\App\Enums\WardType::cases() as $t)<option value="{{ $t->value }}" @selected(old('ward_type', $config->ward_type->value) === $t->value)>{{ $t->label() }}</option>@endforeach</select>
            <label class="inline"><input type="checkbox" name="apply_defaults" value="1"> Reset buttons, tabs and obs chart to this type's defaults</label>

            <div class="two">
                <div><label>Bed layout</label><select name="bed_layout">@foreach (\App\Enums\BedLayout::options() as $v => $l)<option value="{{ $v }}" @selected(old('bed_layout', $config->bed_layout->value) === $v)>{{ $l }}</option>@endforeach</select></div>
                <div><label>Bed count</label><input type="number" name="bed_count" min="1" max="120" value="{{ old('bed_count', $config->bed_count) }}" required>@include('partials.field-error', ['name' => 'bed_count'])</div>
                <div><label>Observation chart</label><select name="obs_chart_type">@foreach (\App\Enums\ObsChartType::options() as $v => $l)<option value="{{ $v }}" @selected(old('obs_chart_type', $config->obs_chart_type->value) === $v)>{{ $l }}</option>@endforeach</select></div>
                <div><label>Default obs frequency</label><select name="obs_frequency_hours">@foreach (config('ward.obs_frequencies') as $v => $l)<option value="{{ $v }}" @selected((int) old('obs_frequency_hours', $config->obs_frequency_hours) === $v)>{{ $l }}</option>@endforeach</select></div>
            </div>
            <label>Rollout</label>
            <select name="rollout_stage">@foreach (\App\Enums\RolloutStage::options() as $v => $l)<option value="{{ $v }}" @selected(old('rollout_stage', $config->rollout_stage->value) === $v)>{{ $l }}</option>@endforeach</select>
            <small>"Not started" hides the ward from clinical staff's ward list.</small>
        </section>
        <section class="panel">
            <h2>Tabs</h2>
            @include('partials.field-error', ['name' => 'enabled_tabs'])
            <div class="checks" style="columns:1">
                @foreach ($allTabs as $key => $tab)
                    <label><input type="checkbox" name="enabled_tabs[]" value="{{ $key }}" data-option-group="tabs" @checked(in_array($key, old('enabled_tabs', $config->enabled_tabs)))>
                        <span><strong>{{ $tab->label() }}</strong><small class="muted">{{ $tab->description() }}</small></span></label>
                @endforeach
            </div>
        </section>
        <section class="panel">
            <h2>Doctor buttons</h2>
            <div class="checks" style="columns:1">
                @foreach ($doctorActions as $key => $a)
                    <label><input type="checkbox" name="doctor_actions[]" value="{{ $key }}" data-option-group="doctor" @checked(in_array($key, old('doctor_actions', $config->doctor_actions)))>
                        <span><strong>{{ $a->label() }}</strong><small class="muted">{{ $a->contains() }}</small></span></label>
                @endforeach
            </div>
        </section>
        <section class="panel">
            <h2>Nurse buttons</h2>
            <div class="checks" style="columns:1">
                @foreach ($nurseActions as $key => $a)
                    <label><input type="checkbox" name="nurse_actions[]" value="{{ $key }}" data-option-group="nurse" @checked(in_array($key, old('nurse_actions', $config->nurse_actions)))>
                        <span><strong>{{ $a->label() }}</strong><small class="muted">{{ $a->contains() }}</small></span></label>
                @endforeach
            </div>
        </section>
    </div>
    <p><button class="btn" type="submit">Save configuration</button> <span class="muted">Every change is logged with who made it.</span></p>
</form>
@if ($changes->isNotEmpty())
    <section class="panel">
        <h2>Recent changes</h2>
        @include('admin.wards._changes', ['changes' => $changes])
    </section>
@endif
@endsection
