@extends('layouts.app')
@section('title', 'Audit trail')

@section('content')
    <div class="page-head"><div><h1>Audit trail</h1>
        <p class="sub">Every critical action, with the values before and after. Records are append-only (FR-170, FR-171).</p></div></div>

    <div class="card"><div class="card-body">
        <form method="GET" class="filters">
            <div class="field"><label>Action</label>
                <select name="action">
                    <option value="">All</option>
                    @foreach ($actions as $action)
                        <option value="{{ $action }}" @selected(request('action') === $action)>{{ $action }}</option>
                    @endforeach
                </select></div>
            <div class="field"><label>User</label>
                <select name="user_id">
                    <option value="">All</option>
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}" @selected(request('user_id') == $user->id)>{{ $user->name }}</option>
                    @endforeach
                </select></div>
            <div class="field"><label>From</label><input type="date" name="from" value="{{ request('from') }}"></div>
            <div class="field"><label>To</label><input type="date" name="to" value="{{ request('to') }}"></div>
            <button type="submit" class="btn secondary">Filter</button>
            <a href="{{ route('admin.audit.index') }}" class="btn secondary">Reset</a>
        </form>
    </div></div>

    <div class="card"><div class="card-body tight table-wrap">
        <table class="data">
            <thead><tr><th>When</th><th>User</th><th>Action</th><th>Record</th><th>Previous value</th><th>New value</th><th>IP</th></tr></thead>
            <tbody>
            @forelse ($logs as $log)
                <tr>
                    <td class="nowrap small">{{ $log->created_at->toDayDateTimeString() }}</td>
                    <td class="small">{{ $log->user?->name ?? $log->user_name ?? 'System' }}</td>
                    <td class="mono small">{{ $log->action }}</td>
                    <td class="small">{{ class_basename($log->auditable_type ?? '') }} @if ($log->auditable_id) #{{ $log->auditable_id }} @endif
                        @if ($log->description)<div class="muted">{{ $log->description }}</div>@endif</td>
                    <td class="small mono" style="max-width:220px;word-break:break-word">{{ $log->old_values ? json_encode($log->old_values, JSON_UNESCAPED_SLASHES) : '—' }}</td>
                    <td class="small mono" style="max-width:220px;word-break:break-word">{{ $log->new_values ? json_encode($log->new_values, JSON_UNESCAPED_SLASHES) : '—' }}</td>
                    <td class="small">{{ $log->ip_address ?: '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty">No audit entries match these filters.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div></div>
    {{ $logs->links() }}
@endsection
