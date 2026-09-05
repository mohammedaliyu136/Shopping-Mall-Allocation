@extends('layouts.app')
@section('title', 'My details')

@section('content')
    {{-- SRS FR-152 - owners update permitted personal information. --}}
    <div class="page-head"><div><h1>My details</h1>
        <p class="sub">Contact and next-of-kin details you may change yourself.</p></div></div>

    <div class="alert info">
        Your name, identification and business registration details are held on the official allocation record.
        To change any of those, raise a support ticket so mall management can review the change.
    </div>

    <div class="card" style="max-width:680px"><div class="card-body">
        <form method="POST" action="{{ route('portal.profile.update') }}">
            @csrf
            @method('PUT')

            <fieldset>
                <legend>On record</legend>
                <dl class="detail">
                    <dt>Owner code</dt><dd class="mono">{{ $owner->owner_code }}</dd>
                    <dt>Full name</dt><dd>{{ $owner->full_name }}</dd>
                    <dt>Identification</dt><dd>{{ $owner->id_type ? str($owner->id_type)->replace('_',' ')->title().' — '.$owner->id_number : '—' }}</dd>
                    <dt>Business</dt><dd>{{ $owner->business_name ?: '—' }}</dd>
                </dl>
            </fieldset>

            <fieldset>
                <legend>Contact details</legend>
                <div class="grid cols-2">
                    <div class="field"><label for="phone">Phone number</label>
                        <input id="phone" type="tel" name="phone" value="{{ old('phone', $owner->phone) }}" required></div>
                    <div class="field"><label for="email">Email address</label>
                        <input id="email" type="email" name="email" value="{{ old('email', $owner->email) }}"></div>
                </div>
                <div class="field"><label for="address">Residential address</label>
                    <input id="address" type="text" name="address" value="{{ old('address', $owner->address) }}"></div>
            </fieldset>

            <fieldset>
                <legend>Next of kin</legend>
                <div class="grid cols-2">
                    <div class="field"><label for="nok_name">Name</label>
                        <input id="nok_name" type="text" name="nok_name" value="{{ old('nok_name', $owner->nok_name) }}"></div>
                    <div class="field"><label for="nok_relationship">Relationship</label>
                        <input id="nok_relationship" type="text" name="nok_relationship" value="{{ old('nok_relationship', $owner->nok_relationship) }}"></div>
                    <div class="field"><label for="nok_phone">Phone</label>
                        <input id="nok_phone" type="tel" name="nok_phone" value="{{ old('nok_phone', $owner->nok_phone) }}"></div>
                    <div class="field"><label for="nok_address">Address</label>
                        <input id="nok_address" type="text" name="nok_address" value="{{ old('nok_address', $owner->nok_address) }}"></div>
                </div>
            </fieldset>

            <button type="submit" class="btn">Save changes</button>
        </form>
    </div></div>
@endsection
