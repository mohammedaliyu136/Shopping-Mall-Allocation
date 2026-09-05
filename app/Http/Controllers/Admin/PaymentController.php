<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\BillingException;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** SRS 6.11 and 6.12 - payment recording, reversal and receipts. */
class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    public function index(Request $request): View
    {
        $payments = Payment::query()
            ->with(['owner', 'invoice.shop', 'receipt', 'receiver'])
            ->when($request->string('method')->toString(), fn ($q, $m) => $q->where('method', $m))
            ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
            ->when($request->string('q')->toString(), fn ($q, $term) => $q->where(
                fn ($sub) => $sub->where('payment_reference', 'like', "%{$term}%")
                    ->orWhereHas('owner', fn ($o) => $o->where('full_name', 'like', "%{$term}%"))
            ))
            ->latest('payment_date')->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.payments.index', [
            'payments' => $payments,
            'methods' => Payment::METHODS,
        ]);
    }

    public function create(Invoice $invoice): View
    {
        $invoice->load(['owner', 'shop.block', 'items']);

        return view('admin.payments.form', ['invoice' => $invoice]);
    }

    /** FR-100 / FR-102 - record a full or partial payment. */
    public function store(Request $request, Invoice $invoice): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_date' => ['required', 'date'],
            'method' => ['required', Rule::in(Payment::METHODS)],
            'payer_name' => ['nullable', 'string', 'max:150'],
            'gateway_reference' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $payment = $this->payments->record($invoice, (string) $validated['amount'], $validated + [
                'received_by' => Auth::id(),
            ]);
        } catch (BillingException $e) {
            return back()->withInput()->withErrors(['amount' => $e->getMessage()]);
        }

        return redirect()->route('admin.payments.show', $payment)->with(
            'status',
            'Payment '.$payment->payment_reference.' recorded. Receipt '.$payment->receipt?->receipt_number.' issued.',
        );
    }

    public function show(Payment $payment): View
    {
        $payment->load(['owner', 'invoice.shop.block', 'invoice.items', 'receipt', 'receiver']);

        return view('admin.payments.show', ['payment' => $payment]);
    }

    public function reverse(Request $request, Payment $payment): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        $this->payments->reverse($payment, $validated['reason']);

        return redirect()->route('admin.payments.show', $payment)
            ->with('status', 'Payment reversed and the invoice balance restored.');
    }
}
