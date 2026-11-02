@extends('layouts.app')
@section('title', 'New ward')
@section('content')
<h1>New ward</h1>
<form method="POST" action="{{ route('admin.wards.store') }}" class="panel" style="max-width:640px">
    @csrf
    <label>Ward name</label><input type="text" name="name" value="{{ old('name') }}" required>
    @include('partials.field-error', ['name' => 'name'])
    <div class="two">
        <div><label>Code</label><input type="text" name="code" value="{{ old('code') }}" required placeholder="e.g. MWB">@include('partials.field-error', ['name' => 'code'])</div>
        <div><label>Specialty</label><input type="text" name="specialty" value="{{ old('specialty') }}"></div>
    </div>
    <label>Ward type</label>
    @foreach ($types as $t)
        <label class="inline" style="display:flex;align-items:flex-start"><input type="radio" name="ward_type" value="{{ $t->value }}" @checked(old('ward_type', 'general') === $t->value)>
            <span><strong>{{ $t->label() }}</strong><br><small>{{ $t->description() }}</small></span></label>
    @endforeach
    <p class="hint">The ward starts with its type's default buttons, tabs, obs chart and beds, so it works immediately. You can adjust them on the next screen.</p>
    <button class="btn" type="submit">Create ward</button>
</form>
@endsection
