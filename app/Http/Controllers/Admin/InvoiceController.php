<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\BillingException;
use App\Http\Controllers\Controller;
use App\Models\Allocation;
use App\Models\BillingCategory;
use App\Models\Invoice;
use App\Services\BillingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/** SRS 6.10 - billing categories, invoices, recurring billing and overdue bills. */
class InvoiceController extends Controller
{
    public function __construct(private readonly BillingService $billing) {}

    public function index(Request $request): View
    {
        $invoices = Invoice::query()
            ->with(['owner', 'shop.block'])
            ->when($request->string('status')->toString(), fn ($q, $s) => $s === 'overdue'
                ? $q->overdue()
                : $q->where('status', $s))
            ->when($request->string('q')->toString(), fn ($q, $term) => $q->where(
                fn ($sub) => $sub->where('invoice_number', 'like', "%{$term}%")
                    ->orWhereHas('owner', fn ($o) => $o->where('full_name', 'like', "%{$term}%"))
            ))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.invoices.index', [
            'invoices' => $invoices,
            'totalOutstanding' => $this->billing->totalOutstanding(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.invoices.form', [
            'allocations' => Allocation::query()
                ->whereIn('status', [Allocation::ACTIVE, Allocation::APPROVED, Allocation::SUSPENDED])
                ->with(['owner', 'shop.block'])
                ->get(),
            'categories' => BillingCategory::query()->where('is_active', true)->orderBy('name')->get(),
            'selectedAllocation' => $request->integer('allocation_id') ?: null,
        ]);
    }

    /** FR-091 - generate an invoice with one or more fee lines. */
    public function store(Request $request): RedirectResponse
    {
        // The form offers several blank spare rows; the unused ones are dropped
        // before validation so they do not read as incomplete fee lines.
        $request->merge(['items' => $this->withoutEmptyLines($request->input('items', []))]);

        $validated = $request->validate([
            'allocation_id' => ['required', 'exists:allocations,id'],
            'billing_period_start' => ['required', 'date'],
            'billing_period_end' => ['required', 'date', 'after:billing_period_start'],
            'due_date' => ['required', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.billing_category_id' => ['nullable', 'exists:billing_categories,id'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_amount' => ['required', 'numeric', 'min:0'],
        ]);

        $allocation = Allocation::findOrFail($validated['allocation_id']);

        // The category is optional, so the key may be absent as well as empty.
        $lines = array_map(fn (array $item) => [
            'category' => filled($item['billing_category_id'] ?? null)
                ? BillingCategory::find($item['billing_category_id'])
                : null,
            'description' => $item['description'],
            'quantity' => $item['quantity'],
            'unit_amount' => $item['unit_amount'],
        ], $validated['items']);

        $invoice = $this->billing->generateInvoice(
            $allocation,
            Carbon::parse($validated['billing_period_start']),
            Carbon::parse($validated['billing_period_end']),
            $lines,
            Carbon::parse($validated['due_date']),
        );

        return redirect()->route('admin.invoices.show', $invoice)
            ->with('status', 'Invoice '.$invoice->invoice_number.' generated.');
    }

    public function show(Invoice $invoice): View
    {
        $invoice->load(['owner', 'shop.block', 'items.billingCategory', 'payments.receipt', 'allocation', 'creator']);

        return view('admin.invoices.show', ['invoice' => $invoice]);
    }

    /**
     * Keeps only the fee rows the user actually filled in: a row counts as used
     * once it carries a description or an amount.
     *
     * @param  array<int|string, mixed>  $items
     * @return array<int, array<string, mixed>>
     */
    private function withoutEmptyLines(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        return array_values(array_filter($items, fn ($item) => is_array($item)
            && (filled($item['description'] ?? null) || filled($item['unit_amount'] ?? null))));
    }

    /** FR-092 - run automatic annual billing for a period. */
    public function runRecurring(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'period_start' => ['nullable', 'date'],
        ]);

        $periodStart = isset($validated['period_start'])
            ? Carbon::parse($validated['period_start'])
            : Carbon::today()->startOfYear();

        $invoices = $this->billing->runRecurringBilling($periodStart);

        return redirect()->route('admin.invoices.index')
            ->with('status', sprintf(
                'Recurring billing run for %s. %d active allocation(s) processed.',
                $periodStart->format('Y'),
                $invoices->count(),
            ));
    }

    /** FR-094 - move unsettled invoices past their due date to overdue. */
    public function flagOverdue(): RedirectResponse
    {
        $count = $this->billing->flagOverdueInvoices();

        return redirect()->route('admin.invoices.index')
            ->with('status', "{$count} overdue reminder(s) sent to owners.");
    }

    public function cancel(Request $request, Invoice $invoice): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        try {
            $this->billing->cancelInvoice($invoice, $validated['reason']);
        } catch (BillingException $e) {
            return back()->withErrors(['invoice' => $e->getMessage()]);
        }

        return redirect()->route('admin.invoices.show', $invoice)->with('status', 'Invoice cancelled.');
    }
}
