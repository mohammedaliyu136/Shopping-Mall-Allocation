<?php

namespace App\Services;

use App\Models\Allocation;
use App\Models\Invoice;
use App\Models\Owner;
use App\Models\OwnershipTransfer;
use App\Models\Payment;
use App\Models\Shop;
use App\Models\SupportTicket;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * SRS 6.17 - allocation, financial, owner and support reporting.
 */
class ReportService
{
    /** FR-160 - allocation reports. */
    public function allocationReport(): array
    {
        $byStatus = Shop::query()
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $totalShops = array_sum($byStatus);
        $allocated = Shop::query()
            ->whereHas('allocations', fn ($q) => $q->whereIn('status', Allocation::OCCUPYING_STATUSES))
            ->count();

        return [
            'total_shops' => $totalShops,
            'available_shops' => $byStatus[Shop::AVAILABLE] ?? 0,
            'allocated_shops' => $allocated,
            'unallocated_shops' => $totalShops - $allocated,
            'shops_by_status' => $byStatus,
            'allocations_by_status' => Allocation::query()
                ->select('status', DB::raw('count(*) as total'))
                ->groupBy('status')
                ->pluck('total', 'status')
                ->all(),
            'occupancy_rate' => $totalShops > 0 ? round($allocated / $totalShops * 100, 1) : 0.0,
        ];
    }

    /** FR-160 - allocation history over a period. */
    public function allocationHistory(?Carbon $from = null, ?Carbon $to = null)
    {
        return Allocation::query()
            ->with(['owner', 'shop.block'])
            ->when($from, fn ($q) => $q->whereDate('allocation_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('allocation_date', '<=', $to))
            ->orderByDesc('allocation_date')
            ->orderByDesc('id')
            ->get();
    }

    /** FR-161 - financial reports. */
    public function financialReport(?Carbon $from = null, ?Carbon $to = null): array
    {
        $invoices = Invoice::query()
            ->where('status', '!=', Invoice::CANCELLED)
            ->when($from, fn ($q) => $q->whereDate('issue_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('issue_date', '<=', $to));

        $payments = Payment::query()
            ->where('status', Payment::CONFIRMED)
            ->when($from, fn ($q) => $q->whereDate('payment_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('payment_date', '<=', $to));

        return [
            'expected_revenue' => $this->money((clone $invoices)->sum('total_amount')),
            'revenue_collected' => $this->money((clone $payments)->sum('amount')),
            'outstanding_balance' => $this->money(
                (clone $invoices)->whereIn('status', Invoice::OPEN_STATUSES)->sum('balance')
            ),
            'overdue_amount' => $this->money(
                (clone $invoices)->overdue()->sum('balance')
            ),
            'overdue_invoices' => (clone $invoices)->overdue()->count(),
            'invoice_count' => (clone $invoices)->count(),
            'payment_count' => (clone $payments)->count(),
            'revenue_by_category' => $this->revenueByCategory($from, $to),
            'revenue_by_method' => (clone $payments)
                ->select('method', DB::raw('sum(amount) as total'))
                ->groupBy('method')
                ->pluck('total', 'method')
                ->map(fn ($v) => $this->money($v))
                ->all(),
            'revenue_by_period' => $this->revenueByMonth($from, $to),
        ];
    }

    /** FR-161 - revenue split by billing category, apportioned across invoice items. */
    public function revenueByCategory(?Carbon $from = null, ?Carbon $to = null): array
    {
        return DB::table('invoice_items')
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->leftJoin('billing_categories', 'billing_categories.id', '=', 'invoice_items.billing_category_id')
            ->where('invoices.status', '!=', Invoice::CANCELLED)
            ->when($from, fn ($q) => $q->whereDate('invoices.issue_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('invoices.issue_date', '<=', $to))
            ->select(
                DB::raw("coalesce(billing_categories.name, 'Uncategorised') as category"),
                DB::raw('sum(invoice_items.amount) as billed'),
            )
            ->groupBy('category')
            ->orderByDesc('billed')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->category => $this->money($row->billed)])
            ->all();
    }

    /** FR-161 - revenue by period. */
    public function revenueByMonth(?Carbon $from = null, ?Carbon $to = null): array
    {
        return Payment::query()
            ->where('status', Payment::CONFIRMED)
            ->when($from, fn ($q) => $q->whereDate('payment_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('payment_date', '<=', $to))
            ->select(
                DB::raw("strftime('%Y-%m', payment_date) as period"),
                DB::raw('sum(amount) as total'),
            )
            ->groupBy('period')
            ->orderBy('period')
            ->pluck('total', 'period')
            ->map(fn ($v) => $this->money($v))
            ->all();
    }

    /** FR-162 - owner reports. */
    public function ownerReport(): array
    {
        return [
            'total_owners' => Owner::query()->count(),
            'active_owners' => Owner::query()->where('status', 'active')->count(),
            'owners_with_allocations' => Owner::query()
                ->whereHas('allocations', fn ($q) => $q->whereIn('status', Allocation::OCCUPYING_STATUSES))
                ->count(),
            'owners_with_outstanding' => Owner::query()
                ->whereHas('invoices', fn ($q) => $q->whereIn('status', Invoice::OPEN_STATUSES)->where('balance', '>', 0))
                ->count(),
            'transfers_by_status' => OwnershipTransfer::query()
                ->select('status', DB::raw('count(*) as total'))
                ->groupBy('status')
                ->pluck('total', 'status')
                ->all(),
            'transfer_history' => OwnershipTransfer::query()
                ->with(['fromOwner', 'toOwner', 'shop'])
                ->orderByDesc('created_at')
                ->limit(50)
                ->get(),
        ];
    }

    /** FR-162 - owners carrying an unsettled balance, largest first. */
    public function ownersWithOutstanding()
    {
        $withBalance = Owner::query()
            ->select('owners.*')
            ->selectSub(
                Invoice::query()
                    ->selectRaw('coalesce(sum(balance), 0)')
                    ->whereColumn('invoices.owner_id', 'owners.id')
                    ->whereIn('status', Invoice::OPEN_STATUSES),
                'outstanding',
            );

        // The computed column is filtered from a derived table rather than with
        // HAVING, which SQLite rejects on a query that does no grouping.
        return Owner::query()
            ->fromSub($withBalance, 'owners')
            ->where('outstanding', '>', 0)
            ->orderByDesc('outstanding')
            ->get();
    }

    /** FR-163 - support reports. */
    public function supportReport(): array
    {
        $byStatus = SupportTicket::query()
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        return [
            'total_tickets' => array_sum($byStatus),
            'open_tickets' => ($byStatus[SupportTicket::OPEN] ?? 0)
                + ($byStatus[SupportTicket::IN_PROGRESS] ?? 0)
                + ($byStatus[SupportTicket::AWAITING_OWNER] ?? 0),
            'resolved_tickets' => ($byStatus[SupportTicket::RESOLVED] ?? 0) + ($byStatus[SupportTicket::CLOSED] ?? 0),
            'tickets_by_status' => $byStatus,
            'tickets_by_category' => SupportTicket::query()
                ->select('category', DB::raw('count(*) as total'))
                ->groupBy('category')
                ->orderByDesc('total')
                ->pluck('total', 'category')
                ->all(),
            'average_resolution_hours' => $this->averageResolutionHours(),
        ];
    }

    /** FR-163 - average time from ticket creation to resolution. */
    public function averageResolutionHours(): ?float
    {
        $average = SupportTicket::query()
            ->whereNotNull('resolved_at')
            ->selectRaw('avg((julianday(resolved_at) - julianday(created_at)) * 24) as hours')
            ->value('hours');

        return $average === null ? null : round((float) $average, 1);
    }

    /** Headline figures for the administration dashboard. */
    public function dashboard(): array
    {
        $allocation = $this->allocationReport();
        $finance = $this->financialReport();
        $support = $this->supportReport();

        return [
            'shops' => $allocation['total_shops'],
            'available_shops' => $allocation['available_shops'],
            'allocated_shops' => $allocation['allocated_shops'],
            'occupancy_rate' => $allocation['occupancy_rate'],
            'owners' => Owner::query()->count(),
            'active_allocations' => Allocation::query()->where('status', Allocation::ACTIVE)->count(),
            'pending_approvals' => Allocation::query()->where('status', Allocation::PENDING_APPROVAL)->count(),
            'pending_transfers' => OwnershipTransfer::query()->where('status', OwnershipTransfer::PENDING)->count(),
            'revenue_collected' => $finance['revenue_collected'],
            'outstanding_balance' => $finance['outstanding_balance'],
            'overdue_invoices' => $finance['overdue_invoices'],
            'open_tickets' => $support['open_tickets'],
        ];
    }

    private function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
