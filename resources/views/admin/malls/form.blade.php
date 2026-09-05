@extends('layouts.app')
@section('title', $mall->exists ? 'Edit mall' : 'Register mall')

@section('content')
    <div class="page-head"><h1>{{ $mall->exists ? 'Edit mall' : 'Register mall' }}</h1></div>

    <div class="card" style="max-width:680px"><div class="card-body">
        <form method="POST" action="{{ $mall->exists ? route('admin.malls.update', $mall) : route('admin.malls.store') }}">
            @csrf
            @if ($mall->exists) @method('PUT') @endif

            <div class="grid cols-2">
                <div class="field">
                    <label for="name">Mall name</label>
                    <input id="name" type="text" name="name" value="{{ old('name', $mall->name) }}" required>
                </div>
                <div class="field">
                    <label for="code">Code</label>
                    <input id="code" type="text" name="code" value="{{ old('code', $mall->code) }}" required>
                    <div class="hint">Used in allocation references, e.g. <span class="mono">GMP/2026/BLK-A/000125</span>.</div>
                </div>
            </div>

            <div class="field">
                <label for="address">Address</label>
                <input id="address" type="text" name="address" value="{{ old('address', $mall->address) }}">
            </div>

            <div class="grid cols-2">
                <div class="field">
                    <label for="phone">Contact phone</label>
                    <input id="phone" type="text" name="phone" value="{{ old('phone', $mall->phone) }}">
                </div>
                <div class="field">
                    <label for="email">Contact email</label>
                    <input id="email" type="email" name="email" value="{{ old('email', $mall->email) }}">
                </div>
            </div>

            <div class="field">
                <label for="description">Description</label>
                <textarea id="description" name="description">{{ old('description', $mall->description) }}</textarea>
            </div>

            <div class="field">
                <label for="status">Status</label>
                <select id="status" name="status">
                    @foreach (['active', 'inactive'] as $status)
                        <option value="{{ $status }}" @selected(old('status', $mall->status ?? 'active') === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
            </div>

            <div class="actions">
                <button type="submit" class="btn">{{ $mall->exists ? 'Save changes' : 'Register mall' }}</button>
                <a href="{{ route('admin.malls.index') }}" class="btn secondary">Cancel</a>
            </div>
        </form>
    </div></div>
@endsection
