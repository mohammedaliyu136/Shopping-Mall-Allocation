@extends('layouts.app')
@section('title', 'New notification')

@section('content')
    <div class="page-head"><h1>Compose notification</h1></div>

    <div class="card" style="max-width:680px"><div class="card-body">
        <form method="POST" action="{{ route('admin.notifications.store') }}">
            @csrf
            <div class="field"><label for="title">Title</label>
                <input id="title" type="text" name="title" value="{{ old('title') }}" required></div>

            <div class="field"><label for="message">Message</label>
                <textarea id="message" name="message" rows="6" required>{{ old('message') }}</textarea></div>

            <div class="grid cols-2">
                {{-- FR-132 --}}
                <div class="field"><label for="channel">Channel</label>
                    <select id="channel" name="channel" required>
                        @foreach ($channels as $channel)
                            <option value="{{ $channel }}" @selected(old('channel') === $channel)>{{ str($channel)->replace('_',' ')->title() }}</option>
                        @endforeach
                    </select></div>

                {{-- FR-131 --}}
                <div class="field"><label for="target_type">Send to</label>
                    <select id="target_type" name="target_type" required>
                        <option value="all" @selected(old('target_type') === 'all')>All owners</option>
                        <option value="owner" @selected(old('target_type') === 'owner')>A specific owner</option>
                        <option value="block" @selected(old('target_type') === 'block')>Owners in a block</option>
                        <option value="outstanding" @selected(old('target_type') === 'outstanding')>Owners with outstanding payments</option>
                    </select></div>
            </div>

            <div class="field"><label for="target_ref">Specific owner or block</label>
                <select id="target_ref" name="target_ref">
                    <option value="">— not required for "all" or "outstanding" —</option>
                    <optgroup label="Owners">
                        @foreach ($owners as $owner)
                            <option value="{{ $owner->id }}" @selected(old('target_ref') == $owner->id)>{{ $owner->full_name }} ({{ $owner->owner_code }})</option>
                        @endforeach
                    </optgroup>
                    <optgroup label="Blocks">
                        @foreach ($blocks as $block)
                            <option value="{{ $block->id }}" @selected(old('target_ref') == $block->id)>{{ $block->mall?->code }} — {{ $block->name }}</option>
                        @endforeach
                    </optgroup>
                </select>
                <div class="hint">Pick the owner when targeting one owner, or the block when targeting a block.</div>
            </div>

            <div class="field"><label><input type="checkbox" name="send_now" value="1" checked> Send immediately</label></div>

            <div class="actions">
                <button type="submit" class="btn">Save notification</button>
                <a href="{{ route('admin.notifications.index') }}" class="btn secondary">Cancel</a>
            </div>
        </form>
    </div></div>
@endsection
