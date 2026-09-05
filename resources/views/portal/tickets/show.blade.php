@extends('layouts.app')
@section('title', $ticket->ticket_number)

@section('content')
    <div class="page-head">
        <div><h1 class="mono">{{ $ticket->ticket_number }}</h1><p class="sub">{{ $ticket->subject }}</p></div>
        <div class="spacer"></div>
        @include('partials.status-badge', ['status' => $ticket->status])
    </div>

    <div class="card"><div class="card-body">
        <dl class="detail">
            <dt>Category</dt><dd>{{ str($ticket->category)->replace('_',' ')->title() }}</dd>
            <dt>Priority</dt><dd>@include('partials.status-badge', ['status' => $ticket->priority])</dd>
            <dt>Shop</dt><dd>{{ $ticket->shop?->label() ?? '—' }}</dd>
            <dt>Raised</dt><dd>{{ $ticket->created_at->toDayDateTimeString() }}</dd>
            <dt>Handled by</dt><dd>{{ $ticket->assignee?->name ?? 'Awaiting assignment' }}</dd>
            <dt>Resolved</dt><dd>{{ $ticket->resolved_at?->toDayDateTimeString() ?? '—' }}</dd>
        </dl>
    </div></div>

    <div class="card">
        <div class="card-head"><h2>Conversation</h2></div>
        <div class="card-body">
            @foreach ($ticket->messages as $message)
                <div class="message {{ $message->user_id ? 'staff' : '' }}">
                    <div class="meta"><strong>{{ $message->user_id ? ($message->user?->name.' (mall management)') : 'You' }}</strong>
                        &middot; {{ $message->created_at->toDayDateTimeString() }}</div>
                    <div style="white-space:pre-line">{{ $message->body }}</div>
                </div>
            @endforeach

            @if ($ticket->status !== \App\Models\SupportTicket::CLOSED)
                <form method="POST" action="{{ route('portal.tickets.reply', $ticket) }}" class="mt">
                    @csrf
                    <div class="field"><label for="body">Add a response</label>
                        <textarea id="body" name="body" required></textarea></div>
                    <button type="submit" class="btn">Send response</button>
                </form>
            @else
                <p class="muted mb0">This ticket is closed. Raise a new ticket if you need further help.</p>
            @endif
        </div>
    </div>
@endsection
