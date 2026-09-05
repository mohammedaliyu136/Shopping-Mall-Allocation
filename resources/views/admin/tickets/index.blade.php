@extends('layouts.app')
@section('title', 'Support tickets')

@section('content')
    <div class="page-head"><div><h1>Support tickets</h1>
        <p class="sub">Owner requests and complaints (FR-140 to FR-143).</p></div></div>

    <div class="card"><div class="card-body">
        <form method="GET" class="filters">
            <div class="field"><label>Search</label><input type="search" name="q" value="{{ request('q') }}" placeholder="Ticket number or subject"></div>
            <div class="field"><label>Status</label>
                <select name="status">
                    <option value="">All</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ str($status)->replace('_',' ')->title() }}</option>
                    @endforeach
                </select></div>
            <div class="field"><label>Category</label>
                <select name="category">
                    <option value="">All</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category }}" @selected(request('category') === $category)>{{ str($category)->replace('_',' ')->title() }}</option>
                    @endforeach
                </select></div>
            <button type="submit" class="btn secondary">Filter</button>
            <a href="{{ route('admin.tickets.index') }}" class="btn secondary">Reset</a>
        </form>
    </div></div>

    <div class="card"><div class="card-body tight table-wrap">
        <table class="data">
            <thead><tr><th>Ticket</th><th>Subject</th><th>Owner</th><th>Category</th>
                <th>Priority</th><th>Assigned to</th><th>Status</th><th>Raised</th></tr></thead>
            <tbody>
            @forelse ($tickets as $ticket)
                <tr>
                    <td><a href="{{ route('admin.tickets.show', $ticket) }}" class="mono">{{ $ticket->ticket_number }}</a></td>
                    <td>{{ $ticket->subject }}</td>
                    <td>{{ $ticket->owner?->full_name }}</td>
                    <td>{{ str($ticket->category)->replace('_',' ')->title() }}</td>
                    <td>@include('partials.status-badge', ['status' => $ticket->priority])</td>
                    <td class="small">{{ $ticket->assignee?->name ?? 'Unassigned' }}</td>
                    <td>@include('partials.status-badge', ['status' => $ticket->status])</td>
                    <td class="nowrap small">{{ $ticket->created_at->toFormattedDateString() }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="empty">No tickets match these filters.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div></div>
    {{ $tickets->links() }}
@endsection
