@extends('layouts.app')
@section('title', $ticket->ticket_number)

@section('content')
    <div class="page-head">
        <div><h1 class="mono">{{ $ticket->ticket_number }}</h1><p class="sub">{{ $ticket->subject }}</p></div>
        <div class="spacer"></div>
        @include('partials.status-badge', ['status' => $ticket->status])
    </div>

    <div class="grid cols-2">
        <div class="card"><div class="card-head"><h2>Ticket</h2></div><div class="card-body">
            <dl class="detail">
                <dt>Owner</dt><dd><a href="{{ route('admin.owners.show', $ticket->owner) }}">{{ $ticket->owner?->full_name }}</a></dd>
                <dt>Shop</dt><dd>{{ $ticket->shop?->label() ?? '—' }}</dd>
                <dt>Category</dt><dd>{{ str($ticket->category)->replace('_',' ')->title() }}</dd>
                <dt>Priority</dt><dd>@include('partials.status-badge', ['status' => $ticket->priority])</dd>
                <dt>Raised</dt><dd>{{ $ticket->created_at->toDayDateTimeString() }}</dd>
                <dt>Assigned to</dt><dd>{{ $ticket->assignee?->name ?? 'Unassigned' }}
                    @if ($ticket->assigned_department) ({{ $ticket->assigned_department }}) @endif</dd>
                <dt>Resolved</dt><dd>{{ $ticket->resolved_at?->toDayDateTimeString() ?? '—' }}</dd>
            </dl>
        </div></div>

        @can(\App\Support\Permissions::TICKETS_MANAGE)
            <div class="card"><div class="card-head"><h2>Handling</h2></div><div class="card-body">
                <form method="POST" action="{{ route('admin.tickets.assign', $ticket) }}">
                    @csrf
                    <div class="field"><label for="assigned_to">Assign to</label>
                        <select id="assigned_to" name="assigned_to">
                            <option value="">Unassigned</option>
                            @foreach ($officers as $officer)
                                <option value="{{ $officer->id }}" @selected($ticket->assigned_to === $officer->id)>{{ $officer->name }}</option>
                            @endforeach
                        </select></div>
                    <div class="field"><label for="assigned_department">Department</label>
                        <input id="assigned_department" type="text" name="assigned_department" value="{{ $ticket->assigned_department }}"></div>
                    <button type="submit" class="btn sm">Update assignment</button>
                </form>

                <hr style="border:none;border-top:1px solid var(--line);margin:1rem 0">

                <form method="POST" action="{{ route('admin.tickets.status', $ticket) }}" class="filters">
                    @csrf
                    <div class="field"><label>Status</label>
                        <select name="status">
                            @foreach ($statuses as $status)
                                <option value="{{ $status }}" @selected($ticket->status === $status)>{{ str($status)->replace('_',' ')->title() }}</option>
                            @endforeach
                        </select></div>
                    <button type="submit" class="btn sm secondary">Update status</button>
                </form>
            </div></div>
        @endcan
    </div>

    {{-- FR-143 - the whole conversation stays attached to the ticket. --}}
    <div class="card">
        <div class="card-head"><h2>Conversation</h2></div>
        <div class="card-body">
            @foreach ($ticket->messages as $message)
                <div class="message {{ $message->is_internal ? 'internal' : ($message->user_id ? 'staff' : '') }}">
                    <div class="meta">
                        <strong>{{ $message->authorName() }}</strong>
                        {{ $message->user_id ? '(staff)' : '(owner)' }}
                        &middot; {{ $message->created_at->toDayDateTimeString() }}
                        @if ($message->is_internal) &middot; <span class="badge warn">Internal note</span> @endif
                    </div>
                    <div style="white-space:pre-line">{{ $message->body }}</div>
                </div>
            @endforeach

            @can(\App\Support\Permissions::TICKETS_MANAGE)
                <form method="POST" action="{{ route('admin.tickets.reply', $ticket) }}" class="mt">
                    @csrf
                    <div class="field"><label for="body">Reply</label>
                        <textarea id="body" name="body" required></textarea></div>
                    <div class="field"><label><input type="checkbox" name="is_internal" value="1">
                            Internal note — not shown to the owner</label></div>
                    <button type="submit" class="btn">Post reply</button>
                </form>
            @endcan
        </div>
    </div>

    @if ($ticket->attachments->isNotEmpty())
        <div class="card"><div class="card-head"><h2>Attachments</h2></div>
            <div class="card-body"><ul class="small">
                @foreach ($ticket->attachments as $attachment)
                    <li>{{ $attachment->original_name ?? basename($attachment->file_path) }}</li>
                @endforeach
            </ul></div></div>
    @endif
@endsection
