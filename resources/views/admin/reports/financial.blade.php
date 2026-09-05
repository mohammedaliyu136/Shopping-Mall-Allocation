@extends('layouts.app')
@section('title', 'Financial report')

@section('content')
    <div class="page-head">
        <div><h1>Financial report</h1><p class="sub">FR-161</p></div>
        <div class="spacer"></div>
        <form method="GET" class="filters">
            <div class="field"><label>From</label><input type="date" name="from" value="{{ request('from') }}"></div>
            <div class="field"><label>To</label><input type="date" name="to" value="{{ request('to') }}"></div>
            <button type="submit" class="btn sm secondary">Apply</button>
        </form>
        <a href="{{ route('admin.reports.export', ['report' => 'financial', 'from' => request('from'), 'to' => request('to')]) }}" class="btn secondary">Export CSV</a>
    </div>

    <div class="grid cols-4">
        <div class="stat"><div class="label">Expected revenue</div>
            <div class="value sm">@include('partials.money', ['amount' => $report['expected_revenue']])</div>
            <div class="note">{{ $report['invoice_count'] }} invoice(s)</div></div>
        <div class="stat"><div class="label">Revenue collected</div>
            <div class="value sm">@include('partials.money', ['amount' => $report['revenue_collected']])</div>
            <div class="note">{{ $report['payment_count'] }} payment(s)</div></div>
        <div class="stat"><div class="label">Outstanding</div>
            <div class="value sm">@include('partials.money', ['amount' => $report['outstanding_balance']])</div></div>
        <div class="stat"><div class="label">Overdue</div>
            <div class="value sm">@include('partials.money', ['amount' => $report['overdue_amount']])</div>
            <div class="note">{{ $report['overdue_invoices'] }} invoice(s)</div></div>
    </div>

    <div class="grid cols-3 mt">
        <div class="card"><div class="card-head"><h2>Revenue by category</h2></div><div class="card-body tight table-wrap">
            <table class="data"><thead><tr><th>Category</th><th class="num">Billed</th></tr></thead><tbody>
            @forelse ($report['revenue_by_category'] as $category => $amount)
                <tr><td>{{ $category }}</td><td class="num">@include('partials.money', ['amount' => $amount])</td></tr>
            @empty
                <tr><td colspan="2" class="empty">No billing in this period.</td></tr>
            @endforelse
            </tbody></table>
        </div></div>

        <div class="card"><div class="card-head"><h2>Revenue by method</h2></div><div class="card-body tight table-wrap">
            <table class="data"><thead><tr><th>Method</th><th class="num">Collected</th></tr></thead><tbody>
            @forelse ($report['revenue_by_method'] as $method => $amount)
                <tr><td>{{ str($method)->replace('_',' ')->title() }}</td><td class="num">@include('partials.money', ['amount' => $amount])</td></tr>
            @empty
                <tr><td colspan="2" class="empty">No payments in this period.</td></tr>
            @endforelse
            </tbody></table>
        </div></div>

        <div class="card"><div class="card-head"><h2>Revenue by period</h2></div><div class="card-body tight table-wrap">
            <table class="data"><thead><tr><th>Month</th><th class="num">Collected</th></tr></thead><tbody>
            @forelse ($report['revenue_by_period'] as $period => $amount)
                <tr><td>{{ $period }}</td><td class="num">@include('partials.money', ['amount' => $amount])</td></tr>
            @empty
                <tr><td colspan="2" class="empty">No payments in this period.</td></tr>
            @endforelse
            </tbody></table>
        </div></div>
    </div>
@endsection
