@extends('layouts.app')
@section('title', 'Notifications')

@section('content')
    <div class="page-head"><div><h1>Notifications</h1></div></div>

    <div class="card"><div class="card-body">
        @forelse ($recipients as $recipient)
            <div class="message">
                <div class="meta">
                    {{ $recipient->created_at->toDayDateTimeString() }}
                    &middot; {{ str($recipient->channel)->replace('_',' ')->title() }}
                    @unless ($recipient->read_at) &middot; <span class="badge warn">New</span> @endunless
                </div>
                <strong>{{ $recipient->notification?->title }}</strong>
                <div style="white-space:pre-line">{{ $recipient->notification?->message }}</div>
                @unless ($recipient->read_at)
                    <form method="POST" action="{{ route('portal.notifications.read', $recipient) }}" class="mt">
                        @csrf<button type="submit" class="btn sm secondary">Mark as read</button>
                    </form>
                @endunless
            </div>
        @empty
            <p class="muted mb0">You have no notifications.</p>
        @endforelse
    </div></div>
    {{ $recipients->links() }}
@endsection
