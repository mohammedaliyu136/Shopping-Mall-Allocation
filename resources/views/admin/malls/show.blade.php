@extends('layouts.app')
@section('title', $mall->name)

@section('content')
    <div class="page-head">
        <div><h1>{{ $mall->name }}</h1><p class="sub mono">{{ $mall->code }}</p></div>
        <div class="spacer"></div>
        @can(\App\Support\Permissions::MALLS_MANAGE)
            <a href="{{ route('admin.malls.edit', $mall) }}" class="btn secondary">Edit</a>
        @endcan
    </div>

    <div class="card"><div class="card-body">
        <dl class="detail">
            <dt>Address</dt><dd>{{ $mall->address ?: '—' }}</dd>
            <dt>Phone</dt><dd>{{ $mall->phone ?: '—' }}</dd>
            <dt>Email</dt><dd>{{ $mall->email ?: '—' }}</dd>
            <dt>Status</dt><dd>@include('partials.status-badge', ['status' => $mall->status])</dd>
            <dt>Description</dt><dd>{{ $mall->description ?: '—' }}</dd>
        </dl>
    </div></div>

    {{-- FR-021 - blocks and floors. --}}
    <div class="card">
        <div class="card-head"><h2>Structure</h2></div>
        <div class="card-body">
            @forelse ($mall->blocks as $block)
                <div class="card">
                    <div class="card-head">
                        <h3>{{ $block->name }} <span class="mono muted">({{ $block->code }})</span></h3>
                        <div class="spacer"></div>
                        <span class="badge muted">{{ $block->shops_count ?? $block->shops()->count() }} shops</span>
                    </div>
                    <div class="card-body">
                        <p class="small mb0">
                            <strong>Floors:</strong>
                            @forelse ($block->floors as $floor)
                                <span class="badge">{{ $floor->name }} ({{ $floor->code }})</span>
                            @empty
                                <span class="muted">none defined</span>
                            @endforelse
                        </p>

                        @can(\App\Support\Permissions::MALLS_MANAGE)
                            <form method="POST" action="{{ route('admin.blocks.floors.store', $block) }}" class="filters mt">
                                @csrf
                                <div class="field"><label>Floor name</label><input type="text" name="name" required></div>
                                <div class="field"><label>Code</label><input type="text" name="code" required></div>
                                <div class="field"><label>Level</label><input type="number" name="level" value="0" required></div>
                                <button type="submit" class="btn sm">Add floor</button>
                            </form>
                        @endcan
                    </div>
                </div>
            @empty
                <p class="muted">No blocks defined for this mall yet.</p>
            @endforelse

            @can(\App\Support\Permissions::MALLS_MANAGE)
                <form method="POST" action="{{ route('admin.malls.blocks.store', $mall) }}" class="filters mt">
                    @csrf
                    <div class="field"><label>New block name</label><input type="text" name="name" required></div>
                    <div class="field"><label>Code</label><input type="text" name="code" placeholder="BLK-C" required></div>
                    <div class="field"><label>Description</label><input type="text" name="description"></div>
                    <button type="submit" class="btn sm">Add block</button>
                </form>
            @endcan
        </div>
    </div>
@endsection
