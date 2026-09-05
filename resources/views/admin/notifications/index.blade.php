@extends('layouts.app')
@section('title', 'Notifications')

@section('content')
    <div class="page-head">
        <div><h1>Notifications</h1><p class="sub">Manual and automated messages to owners (FR-130 to FR-133).</p></div>
        <div class="spacer"></div>
        <a href="{{ route('admin.notifications.create') }}" class="btn">New notification</a>
    </div>

    <div class="card"><div class="card-body tight table-wrap">
        <table class="data">
            <thead><tr><th>Title</th><th>Channel</th><th>Target</th><th>Trigger</th>
                <th class="num">Recipients</th><th>Status</th><th>Sent</th></tr></thead>
            <tbody>
            @forelse ($notifications as $notification)
                <tr>
                    <td><a href="{{ route('admin.notifications.show', $notification) }}">{{ $notification->title }}</a></td>
                    <td>{{ str($notification->channel)->replace('_',' ')->title() }}</td>
                    <td>{{ str($notification->target_type)->title() }}</td>
                    <td class="small mono">{{ $notification->event ?: 'manual' }}</td>
                    <td class="num">{{ $notification->recipients_count }}</td>
                    <td>@include('partials.status-badge', ['status' => $notification->status])</td>
                    <td class="nowrap small">{{ $notification->sent_at?->toFormattedDateString() ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty">No notifications yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div></div>
    {{ $notifications->links() }}
@endsection
