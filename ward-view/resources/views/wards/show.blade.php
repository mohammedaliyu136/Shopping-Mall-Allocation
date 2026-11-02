@extends('layouts.app')
@section('title', $ward->name)
@section('content')
{{-- Shell renders from cached config; the patient list streams in separately. --}}
<div data-ward-config="{{ route('wards.config', $ward) }}" data-ward-id="{{ $ward->id }}" data-config-version="{{ $config->version }}">
    <div class="row" style="margin-bottom:12px">
        <div class="grow">
            <h1>{{ $ward->name }}</h1>
            <div class="muted">
                {{ $config->ward_type->label() }} · {{ $config->bed_layout->label() }} · {{ $config->bed_count }} {{ $config->ward_type === \App\Enums\WardType::Emergency ? 'cubicles' : 'beds' }}
                · obs {{ strtolower(config('ward.obs_frequencies')[$config->obs_frequency_hours] ?? $config->obs_frequency_hours.'-hourly') }}
                @if ($config->rollout_stage === \App\Enums\RolloutStage::Pilot) · <span class="pill blue">Pilot ward</span> @endif
            </div>
        </div>
        <a class="btn secondary" href="{{ route('handover.index', $ward) }}">Handover &amp; tasks @if($openTasks)<span class="pill amber">{{ $openTasks }}</span>@endif</a>
        @if ($config->ward_type === \App\Enums\WardType::Emergency && auth()->user()->isClinical())
            <button class="btn" data-open-dialog="arrival-dialog">+ Register arrival</button>
        @endif
        @if ($config->ward_type === \App\Enums\WardType::CriticalCare && auth()->user()->isNurse())
            <a class="btn secondary" href="{{ route('wards.show', $ward) }}?mine=1" data-mine>My 1:1 patients</a>
        @endif
    </div>

    <div id="patient-list" data-src="{{ route('wards.patients', $ward) }}{{ request('mine') ? '?mine=1' : '' }}" data-refresh="60">
        <div class="skeleton"></div><div class="skeleton"></div><div class="skeleton"></div>
    </div>
</div>
@endsection

@if ($config->ward_type === \App\Enums\WardType::Emergency)
@push('dialogs')
<dialog id="arrival-dialog">
    <form method="POST" action="{{ route('wards.arrivals', $ward) }}">
        @csrf
        <div class="dlg-head"><div class="grow"><h2>Register arrival</h2><small>Adds the patient to the A&amp;E board.</small></div><button class="link" data-close type="button">Close</button></div>
        <div class="dlg-body">
            <div class="two">
                <div><label>Name</label><input type="text" name="name" required></div>
                <div><label>Hospital number (if known)</label><input type="text" name="mrn"></div>
                <div><label>Date of birth</label><input type="date" name="date_of_birth" required max="{{ now()->toDateString() }}"></div>
                <div><label>Sex</label><select name="sex"><option value="female">Female</option><option value="male">Male</option><option value="other">Other</option></select></div>
            </div>
            <label>Presenting complaint</label><input type="text" name="presenting_complaint" required>
            <label>Triage now (optional)</label>
            <select name="triage_category"><option value="">Triage later</option>@foreach (\App\Enums\TriageCategory::options() as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select>
        </div>
        <div class="dlg-foot"><button class="btn secondary" type="button" data-close>Cancel</button><button class="btn" type="submit">Add to board</button></div>
    </form>
</dialog>
@endpush
@endif
