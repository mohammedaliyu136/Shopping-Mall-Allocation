@extends('layouts.app')
@section('title', 'Choose a ward')
@section('content')
<h1>Choose a ward</h1>
<p class="muted">The ward's setup is loaded once when you select it.</p>
@foreach ($wards as $type => $group)
    <h2 style="margin-top:18px">{{ $type }}</h2>
    <div class="grid cols-3">
        @foreach ($group as $ward)
            <form method="POST" action="{{ route('wards.select', $ward) }}" class="panel" style="margin:0">
                @csrf
                <div class="row">
                    <div class="grow">
                        <strong>{{ $ward->name }}</strong>
                        <div class="muted">{{ $ward->specialty ?? $ward->code }}</div>
                    </div>
                    @if ($ward->config?->rollout_stage === \App\Enums\RolloutStage::Pilot)
                        <span class="pill blue">Pilot</span>
                    @endif
                </div>
                <p class="muted" style="margin:.5em 0">
                    {{ $ward->active_encounters_count }} patient{{ $ward->active_encounters_count === 1 ? '' : 's' }}
                    @if ($ward->config) · {{ $ward->config->bed_count }} {{ $ward->config->ward_type === \App\Enums\WardType::Emergency ? 'cubicles' : 'beds' }} @endif
                </p>
                <button class="btn sm" type="submit">Open ward</button>
            </form>
        @endforeach
    </div>
@endforeach
@endsection
