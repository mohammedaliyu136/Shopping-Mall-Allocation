@extends('layouts.app')
@section('title', $encounter->patient->name)
@section('content')
@php($p = $encounter->patient)
@php($user = auth()->user())

{{-- Header: identity and status badges. Status is never a button. --}}
<section class="panel patient-header">
    <div class="row">
        <div class="grow">
            <a href="{{ route('wards.show', session('ward_id') ?? $encounter->ward_id) }}" class="muted">← {{ $encounter->ward->name }}</a>
            <h1>{{ $p->name }}</h1>
            <div class="meta">
                {{ $p->age() }} y · {{ ucfirst($p->sex) }} · MRN {{ $p->mrn }}
                · {{ $encounter->bed ? 'Bed '.$encounter->bed->label : ($config->ward_type->admitsPatients() ? 'No bed' : 'A&E') }}
                · {{ $encounter->team?->name ?? 'No team' }}
                @if ($encounter->assignedNurse) · 1:1 nurse {{ $encounter->assignedNurse->name }} @endif
                · {{ $encounter->status->label() }} {{ ($encounter->admitted_at ?? $encounter->arrived_at)->diffForHumans() }}
            </div>
            @if ($encounter->diagnoses)
                <div style="margin-top:4px"><strong>Problems:</strong> {{ implode(' · ', $encounter->diagnoses) }}</div>
            @elseif ($encounter->presenting_complaint)
                <div style="margin-top:4px"><strong>Presenting:</strong> {{ $encounter->presenting_complaint }}</div>
            @endif
        </div>
        @if ($encounter->triage_category)
            <span class="badge"><span class="triage {{ $encounter->triage_category->colour() }}"></span>{{ $encounter->triage_category->label() }}</span>
        @endif
    </div>
    <div class="badges">
        @foreach ($header->badges() as $b)
            @php($canEdit = $b['edit'] && (in_array($b['edit'], ['resus', 'critical']) ? $user->isDoctor() : (str_starts_with($b['edit'], 'risk:') ? $user->isNurse() : $user->isClinical())))
            @if ($canEdit)
                <button type="button" class="badge {{ $b['tone'] }}" title="{{ $b['title'] }}" data-open-dialog="edit-{{ str_replace(':', '-', $b['edit']) }}">{{ $b['label'] }} ✎</button>
            @else
                <span class="badge {{ $b['tone'] }}" title="{{ $b['title'] }}">{{ $b['label'] }}</span>
            @endif
        @endforeach
    </div>
    @if ($encounter->pendingTransfer && $user->isDoctor())
        <form method="POST" action="{{ route('transfers.cancel', $encounter->pendingTransfer) }}" style="margin-top:8px">@csrf
            <small class="muted">Transfer to {{ $encounter->pendingTransfer->toWard->name }} requested {{ $encounter->pendingTransfer->created_at->diffForHumans() }}.</small>
            <button class="link" type="submit">Cancel transfer</button>
        </form>
    @endif
</section>

{{-- Nurse discharge checklist: appears only once the doctor has started a discharge. --}}
@if ($encounter->isDischargePending() && $user->isNurse())
    @include('encounters.partials.discharge-checklist')
@endif

{{-- Role-specific action bar, built from the ward's enabled keys. --}}
@if ($buttons->isNotEmpty())
    <p class="role-label">{{ $user->role->label() }} actions</p>
    <nav class="action-bar" aria-label="{{ $user->role->label() }} actions">
        @foreach ($buttons as $action)
            @php($reason = $action->unavailableReason($encounter))
            <button type="button" class="action-btn {{ $reason ? 'unavailable' : '' }}" data-action-url="{{ route('actions.form', [$encounter, $action->key()]) }}" title="{{ $reason }}" data-key="{{ $action->key() }}">
                <span class="ico" aria-hidden="true">{{ $action->icon() }}</span>
                <strong>{{ $action->label() }}</strong>
                <small>{{ $reason ?? $action->contains() }}</small>
            </button>
        @endforeach
    </nav>
@endif

@if ($tabs->isNotEmpty())
    <nav class="tabs" data-tabs>
        @foreach ($tabs as $key => $tab)
            <a href="{{ route('encounters.show', $encounter) }}?tab={{ $key }}" data-tab="{{ $key }}" data-tab-src="{{ route('encounters.tab', [$encounter, $key]) }}" class="{{ $key === $activeTab ? 'active' : '' }}" title="{{ $tab->description() }}">{{ $tab->label() }}</a>
        @endforeach
    </nav>
    <section class="tab-body" id="tab-body" data-src="{{ route('encounters.tab', [$encounter, $activeTab]) }}">
        <div class="skeleton"></div><div class="skeleton"></div>
    </section>
@endif
@endsection

@push('dialogs')
    @include('encounters.partials.header-dialogs')
    @if ($openAction && $buttons->has($openAction))
        {{-- Validation failed: re-open the same form with the user's input. --}}
        @php($action = $buttons->get($openAction))
        <dialog id="action-reopen" data-open-on-load>
            @include('actions.dialog', ['action' => $action, 'encounter' => $encounter, 'unavailable' => null] + $action->viewData($encounter))
        </dialog>
    @endif
@endpush
