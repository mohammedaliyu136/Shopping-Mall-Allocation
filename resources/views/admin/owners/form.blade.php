@extends('layouts.app')
@section('title', $owner->exists ? 'Edit owner' : 'Register owner')

@section('content')
    <div class="page-head"><h1>{{ $owner->exists ? 'Edit '.$owner->full_name : 'Register owner' }}</h1></div>

    <div class="card" style="max-width:860px"><div class="card-body">
        <form method="POST" enctype="multipart/form-data"
              action="{{ $owner->exists ? route('admin.owners.update', $owner) : route('admin.owners.store') }}">
            @csrf
            @if ($owner->exists) @method('PUT') @endif

            {{-- FR-040 personal information --}}
            <fieldset>
                <legend>Personal information</legend>
                <div class="grid cols-2">
                    <div class="field"><label for="full_name">Full name</label>
                        <input id="full_name" type="text" name="full_name" value="{{ old('full_name', $owner->full_name) }}" required></div>
                    <div class="field"><label for="gender">Gender</label>
                        <select id="gender" name="gender">
                            <option value="">—</option>
                            @foreach (['male', 'female', 'other'] as $g)
                                <option value="{{ $g }}" @selected(old('gender', $owner->gender) === $g)>{{ ucfirst($g) }}</option>
                            @endforeach
                        </select></div>
                    <div class="field"><label for="date_of_birth">Date of birth</label>
                        <input id="date_of_birth" type="date" name="date_of_birth" value="{{ old('date_of_birth', $owner->date_of_birth?->toDateString()) }}"></div>
                    <div class="field"><label for="nationality">Nationality</label>
                        <input id="nationality" type="text" name="nationality" value="{{ old('nationality', $owner->nationality) }}"></div>
                    <div class="field"><label for="photo">Passport photograph</label>
                        <input id="photo" type="file" name="photo" accept="image/*">
                        @if ($owner->photo_path)<div class="hint">A photograph is already on file; uploading replaces it.</div>@endif
                    </div>
                    <div class="field"><label for="status">Status</label>
                        <select id="status" name="status" required>
                            @foreach (['active', 'inactive', 'suspended'] as $s)
                                <option value="{{ $s }}" @selected(old('status', $owner->status ?? 'active') === $s)>{{ ucfirst($s) }}</option>
                            @endforeach
                        </select></div>
                </div>
            </fieldset>

            {{-- FR-040 contact information --}}
            <fieldset>
                <legend>Contact information</legend>
                <div class="grid cols-2">
                    <div class="field"><label for="phone">Phone number</label>
                        <input id="phone" type="tel" name="phone" value="{{ old('phone', $owner->phone) }}" required></div>
                    <div class="field"><label for="email">Email address</label>
                        <input id="email" type="email" name="email" value="{{ old('email', $owner->email) }}"></div>
                </div>
                <div class="field"><label for="address">Residential address</label>
                    <input id="address" type="text" name="address" value="{{ old('address', $owner->address) }}"></div>
            </fieldset>

            {{-- FR-040 identification --}}
            <fieldset>
                <legend>Identification</legend>
                <div class="grid cols-2">
                    <div class="field"><label for="id_type">Identification type</label>
                        <select id="id_type" name="id_type">
                            <option value="">—</option>
                            @foreach (['national_id' => 'National ID', 'passport' => 'Passport', 'drivers_licence' => "Driver's licence", 'voters_card' => "Voter's card", 'other' => 'Other'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('id_type', $owner->id_type) === $value)>{{ $label }}</option>
                            @endforeach
                        </select></div>
                    <div class="field"><label for="id_number">Identification number</label>
                        <input id="id_number" type="text" name="id_number" value="{{ old('id_number', $owner->id_number) }}"></div>
                </div>
            </fieldset>

            {{-- FR-040 next of kin --}}
            <fieldset>
                <legend>Next of kin</legend>
                <div class="grid cols-2">
                    <div class="field"><label for="nok_name">Name</label>
                        <input id="nok_name" type="text" name="nok_name" value="{{ old('nok_name', $owner->nok_name) }}"></div>
                    <div class="field"><label for="nok_relationship">Relationship</label>
                        <input id="nok_relationship" type="text" name="nok_relationship" value="{{ old('nok_relationship', $owner->nok_relationship) }}"></div>
                    <div class="field"><label for="nok_phone">Phone number</label>
                        <input id="nok_phone" type="tel" name="nok_phone" value="{{ old('nok_phone', $owner->nok_phone) }}"></div>
                    <div class="field"><label for="nok_address">Address</label>
                        <input id="nok_address" type="text" name="nok_address" value="{{ old('nok_address', $owner->nok_address) }}"></div>
                </div>
            </fieldset>

            {{-- FR-041 business information --}}
            <fieldset>
                <legend>Business information</legend>
                <div class="grid cols-2">
                    <div class="field"><label for="business_name">Business name</label>
                        <input id="business_name" type="text" name="business_name" value="{{ old('business_name', $owner->business_name) }}"></div>
                    <div class="field"><label for="business_reg_number">Registration number</label>
                        <input id="business_reg_number" type="text" name="business_reg_number" value="{{ old('business_reg_number', $owner->business_reg_number) }}"></div>
                    <div class="field"><label for="business_address">Business address</label>
                        <input id="business_address" type="text" name="business_address" value="{{ old('business_address', $owner->business_address) }}"></div>
                    <div class="field"><label for="business_nature">Nature of business</label>
                        <input id="business_nature" type="text" name="business_nature" value="{{ old('business_nature', $owner->business_nature) }}"></div>
                </div>
            </fieldset>

            @unless ($owner->exists)
                <div class="field">
                    <label><input type="checkbox" name="create_portal_account" value="1" checked>
                        Create a self-service portal account for this owner</label>
                    <div class="hint">Requires an email address. The owner sets their own password using the reset link.</div>
                </div>
            @endunless

            <div class="actions">
                <button type="submit" class="btn">{{ $owner->exists ? 'Save changes' : 'Register owner' }}</button>
                <a href="{{ route('admin.owners.index') }}" class="btn secondary">Cancel</a>
            </div>
        </form>
    </div></div>
@endsection
