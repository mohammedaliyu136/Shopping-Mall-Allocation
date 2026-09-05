@extends('layouts.app')
@section('title', 'My support tickets')

@section('content')
    <div class="page-head">
        <div><h1>Support tickets</h1></div>
        <div class="spacer"></div>
        <a href="{{ route('portal.tickets.create') }}" class="btn">Raise a ticket</a>
    </div>

    <div class="card"><div class="card-body tight table-wrap">
        <table class="data">
            <thead><tr><th>Ticket</th><th>Subject</th><th>Category</th><th>Priority</th><th>Status</th><th>Raised</th></tr></thead>
            <tbody>
            @forelse ($tickets as $ticket)
                <tr>
                    <td><a href="{{ route('portal.tickets.show', $ticket) }}" class="mono">{{ $ticket->ticket_number }}</a></td>
                    <td>{{ $ticket->subject }}</td>
                    <td>{{ str($ticket->category)->replace('_',' ')->title() }}</td>
                    <td>@include('partials.status-badge', ['status' => $ticket->priority])</td>
                    <td>@include('partials.status-badge', ['status' => $ticket->status])</td>
                    <td class="nowrap small">{{ $ticket->created_at->toFormattedDateString() }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">You have not raised any tickets.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div></div>
    {{ $tickets->links() }}
@endsection
