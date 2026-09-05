<?php

namespace App\Services;

use App\Exceptions\BillingException;
use App\Models\Allocation;
use App\Models\BillingCategory;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Owner;
use App\Models\SystemSetting;
use App\Support\Money;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * SRS 6.10 - invoice generation, automatic recurring billing, outstanding
 * balances and overdue detection.
 */
class BillingService
{
    public function __construct(
        private readonly ReferenceGenerator $references,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * FR-091 - generate an invoice for an allocation.
     *
     * @param  array<int, array{category?: BillingCategory|null, description: string, quantity?: float|string, unit_amount: float|string}>|null  $lines
     *                                                                                                                                                   Pass null to bill the allocation's standing charges (rent, service charge, other fees).
     */
    public function generateInvoice(
        Allocation $allocation,
        ?Carbon $periodStart = null,
        ?Carbon $periodEnd = null,
        ?array $lines = null,
        ?Carbon $dueDate = null,
    ): Invoice {
        $periodStart ??= Carbon::today()->startOfYear();
        $periodEnd ??= (clone $periodStart)->addYear()->subDay();

        // FR-092 - re-running a billing cycle must not double-bill an allocation.
        $existing = Invoice::query()
            ->where('allocation_id', $allocation->getKey())
            ->whereDate('billing_period_start', $periodStart)
            ->whereDate('billing_period_end', $periodEnd)
            ->first();

        if ($existing) {
            return $existing;
        }

        $lines ??= $this->standingCharges($allocation);

        return DB::transaction(function () use ($allocation, $periodStart, $periodEnd, $lines, $dueDate) {
            $graceDays = (int) SystemSetting::get('billing.due_days', 30);

            $invoice = Invoice::create([
                'invoice_number' => $this->references->invoiceNumber(),
                'allocation_id' => $allocation->getKey(),
                'owner_id' => $allocation->owner_id,
                'shop_id' => $allocation->shop_id,
                'billing_period_start' => $periodStart,
                'billing_period_end' => $periodEnd,
                'issue_date' => Carbon::today(),
                'due_date' => $dueDate ?? Carbon::today()->addDays($graceDays),
                'total_amount' => 0,
                'amount_paid' => 0,
                'balance' => 0,
                'status' => Invoice::UNPAID,
                'created_by' => Auth::id(),
            ]);

            foreach ($lines as $line) {
                $quantity = (string) ($line['quantity'] ?? 1);
                $unit = (string) $line['unit_amount'];

                InvoiceItem::create([
                    'invoice_id' => $invoice->getKey(),
                    'billing_category_id' => $line['category']?->getKey(),
                    'description' => $line['description'],
                    'quantity' => $quantity,
                    'unit_amount' => $unit,
                    'amount' => Money::multiply($unit, $quantity),
                ]);
            }

            $this->recalculate($invoice);

            // FR-133 - the owner is notified when an invoice is generated.
            $this->notifications->invoiceGenerated($invoice->fresh(['owner', 'shop']));

            return $invoice->fresh(['items', 'owner', 'shop']);
        });
    }

    /**
     * FR-092 - run annual billing across every active allocation for a period.
     * Safe to re-run: allocations already billed for the period are skipped.
     *
     * @return Collection<int, Invoice>
     */
    public function runRecurringBilling(?Carbon $periodStart = null): Collection
    {
        $periodStart ??= Carbon::today()->startOfYear();
        $periodEnd = (clone $periodStart)->addYear()->subDay();

        $invoices = new Collection;

        Allocation::query()
            ->where('status', Allocation::ACTIVE)
            ->with(['owner', 'shop'])
            ->each(function (Allocation $allocation) use ($periodStart, $periodEnd, $invoices) {
                $invoices->push($this->generateInvoice($allocation, $periodStart, clone $periodEnd));
            });

        return $invoices;
    }

    /**
     * FR-093 - recompute totals, paid amount, balance and status from the
     * invoice's items and confirmed payments.
     */
    public function recalculate(Invoice $invoice): Invoice
    {
        $total = (string) $invoice->items()->sum('amount');
        $paid = (string) $invoice->confirmedPayments()->sum('amount');
        $total = Money::of($total);
        $paid = Money::of($paid);
        $balance = Money::subtract($total, $paid);

        $status = match (true) {
            $invoice->status === Invoice::CANCELLED => Invoice::CANCELLED,
            Money::isSettled($balance) => Invoice::PAID,
            Money::isPositive($paid) => Invoice::PARTIALLY_PAID,
            default => Invoice::UNPAID,
        };

        // FR-094 - an unsettled invoice past its due date is overdue.
        if ($status !== Invoice::PAID && $status !== Invoice::CANCELLED
            && $invoice->due_date->isBefore(Carbon::today())) {
            $status = Invoice::OVERDUE;
        }

        $invoice->update([
            'total_amount' => $total,
            'amount_paid' => $paid,
            'balance' => $balance,
            'status' => $status,
        ]);

        return $invoice;
    }

    /**
     * FR-094 / FR-133 - notify owners about invoices that have passed their due
     * date without being settled, and make sure the status reflects that.
     *
     * recalculate() already derives the overdue status whenever an invoice is
     * touched, so this run is about the reminder rather than the label: it picks
     * up every unsettled invoice past its due date that has not been reminded
     * about yet, which means an invoice is never reminded about twice and none
     * is missed because its status was already correct. Returns how many owners
     * were notified.
     */
    public function flagOverdueInvoices(): int
    {
        $count = 0;

        Invoice::query()
            ->whereIn('status', Invoice::OPEN_STATUSES)
            ->whereDate('due_date', '<', Carbon::today())
            ->whereNull('overdue_notified_at')
            ->with(['owner', 'shop'])
            ->each(function (Invoice $invoice) use (&$count) {
                $invoice->update([
                    'status' => Invoice::OVERDUE,
                    'overdue_notified_at' => Carbon::now(),
                ]);

                $this->notifications->paymentOverdue($invoice);
                $count++;
            });

        return $count;
    }

    /** FR-093 - the total a single owner still owes. */
    public function outstandingFor(Owner $owner): string
    {
        return $this->asMoney(
            $owner->invoices()->whereIn('status', Invoice::OPEN_STATUSES)->sum('balance')
        );
    }

    /** FR-161 - the total the mall is still owed. */
    public function totalOutstanding(): string
    {
        return $this->asMoney(
            Invoice::query()->whereIn('status', Invoice::OPEN_STATUSES)->sum('balance')
        );
    }

    public function cancelInvoice(Invoice $invoice, string $reason): Invoice
    {
        if ($invoice->confirmedPayments()->exists()) {
            throw BillingException::invoiceNotPayable($invoice);
        }

        $invoice->update(['status' => Invoice::CANCELLED, 'balance' => 0, 'notes' => $reason]);
        $invoice->writeAuditLog('invoice.cancelled', [], ['reason' => $reason]);

        return $invoice->fresh();
    }

    /**
     * The standing annual charges carried by an allocation (FR-090).
     *
     * @return array<int, array{category: BillingCategory|null, description: string, unit_amount: string}>
     */
    private function standingCharges(Allocation $allocation): array
    {
        $lines = [];
        $shopLabel = $allocation->shop?->label() ?? 'Shop';

        $charges = [
            [BillingCategory::ANNUAL_RENT, 'Annual rent - '.$shopLabel, $allocation->annual_rent],
            [BillingCategory::SERVICE_CHARGE, 'Service charge - '.$shopLabel, $allocation->service_charge],
            ['other-fees', 'Other fees - '.$shopLabel, $allocation->other_fees],
        ];

        foreach ($charges as [$code, $description, $amount]) {
            if (Money::isSettled($amount)) {
                continue;
            }

            $lines[] = [
                'category' => BillingCategory::query()->where('code', $code)->first(),
                'description' => $description,
                'quantity' => '1',
                'unit_amount' => (string) $amount,
            ];
        }

        return $lines;
    }

    private function asMoney(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
