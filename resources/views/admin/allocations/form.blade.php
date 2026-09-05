@extends('layouts.app')
@section('title', 'New allocation')

@section('content')
    <div class="page-head">
        <div><h1>Allocate a shop</h1>
            <p class="sub">Only shops without a live allocation are listed — the system prevents duplicate allocation (FR-032).</p></div>
    </div>

    <div class="card" style="max-width:760px"><div class="card-body">
        @if ($shops->isEmpty())
            <p class="alert info mb0">There are no shops available for allocation. Cancel or transfer an existing allocation, or register more shops.</p>
        @else
            <form method="POST" action="{{ route('admin.allocations.store') }}">
                @csrf
                <div class="grid cols-2">
                    <div class="field"><label for="shop_id">Shop</label>
                        <select id="shop_id" name="shop_id" required>
                            <option value="">Select a shop…</option>
                            @foreach ($shops as $shop)
                                <option value="{{ $shop->id }}"
                                        data-rent="{{ $shop->annual_rent }}"
                                    @selected(old('shop_id', $selectedShop) == $shop->id)>
                                    {{ $shop->shop_number }} — {{ $shop->block?->name ?: 'No block' }} ({{ str($shop->shop_type)->title() }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field"><label for="owner_id">Owner</label>
                        <select id="owner_id" name="owner_id" required>
                            <option value="">Select an owner…</option>
                            @foreach ($owners as $owner)
                                <option value="{{ $owner->id }}" @selected(old('owner_id') == $owner->id)>
                                    {{ $owner->full_name }} ({{ $owner->owner_code }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="grid cols-3">
                    <div class="field"><label for="allocation_date">Allocation date</label>
                        <input id="allocation_date" type="date" name="allocation_date" value="{{ old('allocation_date', now()->toDateString()) }}" required></div>
                    <div class="field"><label for="expiry_date">Expiry date</label>
                        <input id="expiry_date" type="date" name="expiry_date" value="{{ old('expiry_date') }}"></div>
                    <div class="field"><label for="allocation_type">Allocation type</label>
                        <select id="allocation_type" name="allocation_type" required>
                            @foreach (['lease' => 'Lease', 'outright_purchase' => 'Outright purchase', 'temporary' => 'Temporary', 'concession' => 'Concession'] as $v => $l)
                                <option value="{{ $v }}" @selected(old('allocation_type', 'lease') === $v)>{{ $l }}</option>
                            @endforeach
                        </select></div>
                </div>

                <fieldset>
                    <legend>Financial obligations</legend>
                    <p class="hint">Leave blank to carry over the charges held against the shop.</p>
                    <div class="grid cols-3">
                        <div class="field"><label for="annual_rent">Annual rent</label>
                            <input id="annual_rent" type="number" step="0.01" min="0" name="annual_rent" value="{{ old('annual_rent') }}"></div>
                        <div class="field"><label for="service_charge">Service charge</label>
                            <input id="service_charge" type="number" step="0.01" min="0" name="service_charge" value="{{ old('service_charge') }}"></div>
                        <div class="field"><label for="other_fees">Other fees</label>
                            <input id="other_fees" type="number" step="0.01" min="0" name="other_fees" value="{{ old('other_fees') }}"></div>
                    </div>
                </fieldset>

                <div class="field"><label for="terms">Terms and conditions</label>
                    <textarea id="terms" name="terms" rows="6">{{ old('terms', \App\Models\SystemSetting::get('allocation.default_terms')) }}</textarea>
                    <div class="hint">Printed on the allocation letter.</div>
                </div>

                <div class="actions">
                    <button type="submit" class="btn">Create draft allocation</button>
                    <a href="{{ route('admin.allocations.index') }}" class="btn secondary">Cancel</a>
                </div>
            </form>
        @endif
    </div></div>
@endsection
