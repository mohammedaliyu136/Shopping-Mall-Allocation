@extends('layouts.app')
@section('title', 'Malls')

@section('content')
    <div class="page-head">
        <div><h1>Malls</h1><p class="sub">Registered properties and their structure (FR-020, FR-021).</p></div>
        <div class="spacer"></div>
        @can(\App\Support\Permissions::MALLS_MANAGE)
            <a href="{{ route('admin.malls.create') }}" class="btn">Register mall</a>
        @endcan
    </div>

    <div class="card"><div class="card-body tight table-wrap">
        <table class="data">
            <thead><tr><th>Name</th><th>Code</th><th>Address</th><th class="num">Blocks</th><th class="num">Shops</th><th>Status</th></tr></thead>
            <tbody>
            @forelse ($malls as $mall)
                <tr>
                    <td><a href="{{ route('admin.malls.show', $mall) }}">{{ $mall->name }}</a></td>
                    <td class="mono">{{ $mall->code }}</td>
                    <td>{{ $mall->address }}</td>
                    <td class="num">{{ $mall->blocks_count }}</td>
                    <td class="num">{{ $mall->shops_count }}</td>
                    <td>@include('partials.status-badge', ['status' => $mall->status])</td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">No malls registered yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div></div>
    {{ $malls->links() }}
@endsection
