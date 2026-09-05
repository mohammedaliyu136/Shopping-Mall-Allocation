@extends('layouts.app')
@section('title', 'Support report')

@section('content')
    <div class="page-head">
        <div><h1>Support report</h1><p class="sub">FR-163</p></div>
        <div class="spacer"></div>
        <a href="{{ route('admin.reports.export', ['report' => 'support']) }}" class="btn secondary">Export CSV</a>
    </div>

    <div class="grid cols-4">
        <div class="stat"><div class="label">Total tickets</div><div class="value">{{ $report['total_tickets'] }}</div></div>
        <div class="stat"><div class="label">Open</div><div class="value">{{ $report['open_tickets'] }}</div></div>
        <div class="stat"><div class="label">Resolved</div><div class="value">{{ $report['resolved_tickets'] }}</div></div>
        <div class="stat"><div class="label">Avg. resolution</div>
            <div class="value sm">{{ $report['average_resolution_hours'] !== null ? $report['average_resolution_hours'].' h' : '—' }}</div></div>
    </div>

    <div class="grid cols-2 mt">
        <div class="card"><div class="card-head"><h2>Tickets by status</h2></div><div class="card-body tight table-wrap">
            <table class="data"><tbody>
            @forelse ($report['tickets_by_status'] as $status => $count)
                <tr><td>@include('partials.status-badge', ['status' => $status])</td><td class="num">{{ $count }}</td></tr>
            @empty
                <tr><td class="empty">No tickets raised.</td></tr>
            @endforelse
            </tbody></table>
        </div></div>

        <div class="card"><div class="card-head"><h2>Tickets by category</h2></div><div class="card-body tight table-wrap">
            <table class="data"><tbody>
            @forelse ($report['tickets_by_category'] as $category => $count)
                <tr><td>{{ str($category)->replace('_',' ')->title() }}</td><td class="num">{{ $count }}</td></tr>
            @empty
                <tr><td class="empty">No tickets raised.</td></tr>
            @endforelse
            </tbody></table>
        </div></div>
    </div>
@endsection
