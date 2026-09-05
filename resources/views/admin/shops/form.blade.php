@extends('layouts.app')
@section('title', $shop->exists ? 'Edit shop' : 'Register shop')

@section('content')
    <div class="page-head"><h1>{{ $shop->exists ? 'Edit shop '.$shop->shop_number : 'Register shop' }}</h1></div>

    <div class="card" style="max-width:760px"><div class="card-body">
        <form method="POST" action="{{ $shop->exists ? route('admin.shops.update', $shop) : route('admin.shops.store') }}">
            @csrf
            @if ($shop->exists) @method('PUT') @endif

            <fieldset>
                <legend>Location</legend>
                <div class="grid cols-3">
                    <div class="field"><label for="mall_id">Mall</label>
                        <select id="mall_id" name="mall_id" required>
                            @foreach ($malls as $mall)
                                <option value="{{ $mall->id }}" @selected(old('mall_id', $shop->mall_id) == $mall->id)>{{ $mall->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field"><label for="block_id">Block</label>
                        <select id="block_id" name="block_id">
                            <option value="">—</option>
                            @foreach ($blocks as $block)
                                <option value="{{ $block->id }}" @selected(old('block_id', $shop->block_id) == $block->id)>{{ $block->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field"><label for="floor_id">Floor</label>
                        <select id="floor_id" name="floor_id">
                            <option value="">—</option>
                            @foreach ($floors as $floor)
                                <option value="{{ $floor->id }}" @selected(old('floor_id', $shop->floor_id) == $floor->id)>{{ $floor->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </fieldset>

            <fieldset>
                <legend>Identification</legend>
                <div class="grid cols-3">
                    <div class="field"><label for="shop_code">Shop ID</label>
                        <input id="shop_code" type="text" name="shop_code" value="{{ old('shop_code', $shop->shop_code) }}" required>
                        <div class="hint">Unique across the system.</div>
                    </div>
                    <div class="field"><label for="shop_number">Shop number</label>
                        <input id="shop_number" type="text" name="shop_number" value="{{ old('shop_number', $shop->shop_number) }}" required>
                    </div>
                    <div class="field"><label for="shop_type">Type</label>
                        <select id="shop_type" name="shop_type">
                            @foreach (['retail', 'anchor', 'kiosk', 'office', 'storage', 'food'] as $type)
                                <option value="{{ $type }}" @selected(old('shop_type', $shop->shop_type ?? 'retail') === $type)>{{ ucfirst($type) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="grid cols-2">
                    <div class="field"><label for="size_sqm">Size (m&sup2;)</label>
                        <input id="size_sqm" type="number" step="0.01" min="0" name="size_sqm" value="{{ old('size_sqm', $shop->size_sqm) }}">
                    </div>
                    <div class="field"><label for="status">Status</label>
                        <select id="status" name="status" required>
                            @foreach (\App\Models\Shop::STATUSES as $status)
                                <option value="{{ $status }}" @selected(old('status', $shop->status ?? 'available') === $status)>{{ str($status)->title() }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </fieldset>

            <fieldset>
                <legend>Charges</legend>
                <div class="grid cols-3">
                    <div class="field"><label for="annual_rent">Annual rent</label>
                        <input id="annual_rent" type="number" step="0.01" min="0" name="annual_rent" value="{{ old('annual_rent', $shop->annual_rent ?? 0) }}" required>
                    </div>
                    <div class="field"><label for="service_charge">Service charge</label>
                        <input id="service_charge" type="number" step="0.01" min="0" name="service_charge" value="{{ old('service_charge', $shop->service_charge ?? 0) }}" required>
                    </div>
                    <div class="field"><label for="other_fees">Other fees</label>
                        <input id="other_fees" type="number" step="0.01" min="0" name="other_fees" value="{{ old('other_fees', $shop->other_fees ?? 0) }}" required>
                    </div>
                </div>
            </fieldset>

            <div class="field"><label for="description">Description</label>
                <textarea id="description" name="description">{{ old('description', $shop->description) }}</textarea>
            </div>

            <div class="actions">
                <button type="submit" class="btn">{{ $shop->exists ? 'Save changes' : 'Register shop' }}</button>
                <a href="{{ route('admin.shops.index') }}" class="btn secondary">Cancel</a>
            </div>
        </form>
    </div></div>
@endsection
