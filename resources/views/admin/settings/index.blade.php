@extends('layouts.app')
@section('title', 'System settings')

@section('content')
    <div class="page-head"><div><h1>System settings</h1>
        <p class="sub">Reference formats, billing periods, approval stages and session policy.</p></div></div>

    <form method="POST" action="{{ route('admin.settings.update') }}">
        @csrf
        @method('PUT')

        @foreach ($grouped as $group => $settings)
            <div class="card">
                <div class="card-head"><h2>{{ ucfirst($group) }}</h2></div>
                <div class="card-body">
                    @foreach ($settings as $setting)
                        <div class="field">
                            <label for="s-{{ $setting->id }}">{{ $setting->key }}</label>
                            @if (strlen((string) $setting->value) > 80 || $setting->type === 'json')
                                <textarea id="s-{{ $setting->id }}" name="settings[{{ $setting->key }}]" rows="5">{{ old('settings.'.$setting->key, $setting->value) }}</textarea>
                            @else
                                <input id="s-{{ $setting->id }}" type="text" name="settings[{{ $setting->key }}]"
                                       value="{{ old('settings.'.$setting->key, $setting->value) }}">
                            @endif
                            <div class="hint">{{ $setting->description }} <span class="mono">({{ $setting->type }})</span></div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach

        <button type="submit" class="btn">Save settings</button>
    </form>
@endsection
