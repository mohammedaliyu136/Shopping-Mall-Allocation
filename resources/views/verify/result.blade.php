@extends('layouts.public')
@section('title', 'Verification result')

@section('content')
    @if (! $allocation)
        {{-- FR-082 - invalid or unrecognised. --}}
        <div class="card">
            <div class="verify-result invalid">
                <div class="mark">&#10007;</div>
                <div class="headline">INVALID OR UNRECOGNISED ALLOCATION</div>
                <p class="muted small">
                    No allocation matches
                    @if ($reference) the reference <span class="mono">{{ $reference }}</span> @else this verification link @endif.
                    If you received this document from a third party, contact mall management before acting on it.
                </p>
                <a href="{{ route('verify.index') }}" class="btn secondary">Try another reference</a>
            </div>
        </div>
    @else
        @php
            // FR-081 / FR-083 - a cancelled or transferred allocation is reported
            // with its true current status rather than simply failing.
            $verified = $allocation->isVerified();
            $tone = $verified ? 'valid' : ($allocation->status === \App\Models\Allocation::SUSPENDED ? 'caution' : 'invalid');
        @endphp

        <div class="card">
            <div class="verify-result {{ $tone }}">
                <div class="mark">{!! $verified ? '&#10003;' : '&#9888;' !!}</div>
                <div class="headline">{{ $allocation->verificationStatus() }}</div>

                <dl class="detail">
                    <dt>Allocation Reference</dt>
                    <dd class="mono">{{ $allocation->reference_number }}</dd>

                    <dt>Mall</dt>
                    <dd>{{ $allocation->mall?->name }}</dd>

                    <dt>Shop</dt>
                    <dd>{{ $allocation->shop?->label() }}</dd>

                    <dt>Allocation Status</dt>
                    <dd>@include('partials.status-badge', ['status' => $allocation->status])</dd>

                    <dt>Allocated To</dt>
                    <dd>{{ $allocation->owner?->full_name }}</dd>

                    @if ($allocation->activeCard)
                        <dt>Card Number</dt>
                        <dd class="mono">{{ $allocation->activeCard->card_number }}</dd>
                    @endif

                    <dt>Allocation Date</dt>
                    <dd>{{ $allocation->allocation_date?->toFormattedDateString() }}</dd>

                    <dt>Verification Date</dt>
                    <dd>{{ now()->toDayDateTimeString() }}</dd>
                </dl>

                @if (! $verified)
                    <p class="alert bad mt small">
                        This document is no longer valid for the holder presenting it. Its current status is
                        <strong>{{ str($allocation->status)->replace('_', ' ')->title() }}</strong>.
                    </p>
                @endif

                @if (! empty($qr))
                    <div class="mt"><span class="qr">{!! $qr !!}</span></div>
                @endif
            </div>
        </div>

        <p class="small muted">
            This result reflects the live record held by mall management at the time of checking.
            <a href="{{ route('verify.index') }}">Verify another document</a>
        </p>
    @endif
@endsection
