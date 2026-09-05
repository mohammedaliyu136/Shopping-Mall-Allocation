@extends('layouts.app')
@section('title', $notification->title)

@section('content')
    <div class="page-head">
        <div><h1>{{ $notification->title }}</h1>
            <p class="sub">{{ str($notification->channel)->replace('_',' ')->title() }} &middot; {{ str($notification->target_type)->title() }}</p></div>
        <div class="spacer"></div>
        @include('partials.status-badge', ['status' => $notification->status])
        @if ($notification->status !== 'sent')
            <form method="POST" action="{{ route('admin.notifications.send', $notification) }}">
                @csrf<button type="submit" class="btn">Send now</button>
            </form>
        @endif
    </div>

    <div class="card"><div class="card-body">
        <p style="white-space:pre-line">{{ $notification->message }}</p>
        <p class="small muted mb0">
            Created by {{ $notification->creator?->name ?? 'System' }} on {{ $notification->created_at->toDayDateTimeString() }}
            @if ($notification->event) &middot; automated trigger <span class="mono">{{ $notification->event }}</span> @endif
        </p>
    </div></div>

    <div class="card">
        <div class="card-head"><h2>Delivery</h2></div>
        <div class="card-body tight table-wrap">
            <table class="data">
                <thead><tr><th>Recipient</th><th>Channel</th><th>Destination</th><th>Status</th><th>Sent</th><th>Read</th><th>Error</th></tr></thead>
                <tbody>
                @forelse ($notification->recipients as $recipient)
                    <tr>
                        <td>{{ $recipient->owner?->full_name ?? $recipient->user?->name ?? '—' }}</td>
                        <td>{{ str($recipient->channel)->replace('_',' ')->title() }}</td>
                        <td class="small">{{ $recipient->destination ?: 'In-system' }}</td>
                        <td>@include('partials.status-badge', ['status' => $recipient->status])</td>
                        <td class="nowrap small">{{ $recipient->sent_at?->toDayDateTimeString() ?? '—' }}</td>
                        <td class="nowrap small">{{ $recipient->read_at?->toDayDateTimeString() ?? '—' }}</td>
                        <td class="small">{{ $recipient->error ?: '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty">No recipients resolved for this notification.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
