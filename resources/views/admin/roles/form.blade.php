@extends('layouts.app')
@section('title', $role->exists ? 'Edit role' : 'New role')

@section('content')
    <div class="page-head"><h1>{{ $role->exists ? 'Edit role: '.$role->name : 'New role' }}</h1></div>

    @if ($role->slug === \App\Models\Role::SUPER_ADMIN)
        <div class="alert info">The Super Administrator has unrestricted access by definition; its permissions cannot be narrowed.</div>
    @endif

    <div class="card"><div class="card-body">
        <form method="POST" action="{{ $role->exists ? route('admin.roles.update', $role) : route('admin.roles.store') }}">
            @csrf
            @if ($role->exists) @method('PUT') @endif

            <div class="grid cols-2">
                <div class="field"><label for="name">Role name</label>
                    <input id="name" type="text" name="name" value="{{ old('name', $role->name) }}" required></div>
                <div class="field"><label for="description">Description</label>
                    <input id="description" type="text" name="description" value="{{ old('description', $role->description) }}"></div>
            </div>

            @if ($role->slug !== \App\Models\Role::SUPER_ADMIN)
                <div class="grid cols-2">
                    @foreach ($grouped as $group => $permissions)
                        <fieldset>
                            <legend>{{ $group }}</legend>
                            @foreach ($permissions as $permission)
                                <div class="field" style="margin-bottom:.3rem">
                                    <label><input type="checkbox" name="permissions[]" value="{{ $permission->id }}"
                                                  @checked(in_array($permission->id, old('permissions', $assigned)))>
                                        {{ $permission->name }}</label>
                                </div>
                            @endforeach
                        </fieldset>
                    @endforeach
                </div>
            @endif

            <div class="actions">
                <button type="submit" class="btn">{{ $role->exists ? 'Save role' : 'Create role' }}</button>
                <a href="{{ route('admin.roles.index') }}" class="btn secondary">Cancel</a>
            </div>
        </form>
    </div></div>
@endsection
