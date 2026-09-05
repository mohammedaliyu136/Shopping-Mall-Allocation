<?php

namespace App\Http\Controllers\Portal;

use App\Exceptions\BillingException;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** SRS FR-150 / FR-151 - the owner's bills, payments and receipts. */
class BillingController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    public function invoices(Request $request): View
    {
        $owner = Auth::user()->owner;

        return view('portal.billing.invoices', [
            'invoices' => $owner->invoices()
                ->with(['shop.block'])
                ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
                ->latest('issue_date')->latest('id')
                ->paginate(20)->withQueryString(),
            'outstanding' => $owner->outstandingBalance(),
        ]);
    }

    public function showInvoice(Invoice $invoice): View
    {
        $this->authorize('view-invoice', $invoice);

        $invoice->load(['items.billingCategory', 'shop.block', 'confirmedPayments.receipt']);

        return view('portal.billing.invoice', ['invoice' => $invoice]);
    }

    public function payments(): View
    {
        $owner = Auth::user()->owner;

        return view('portal.billing.payments', [
            'payments' => $owner->payments()
                ->with(['invoice.shop', 'receipt'])
                ->latest('payment_date')->latest('id')
                ->paginate(20),
        ]);
    }

    /**
     * FR-103 - the owner starts an online payment.
     *
     * The payment is recorded as pending against a generated gateway reference.
     * A real deployment hands that reference to the payment gateway and confirms
     * the payment on the gateway's callback; PaymentService::confirm() is the
     * entry point for that callback and refuses duplicate references.
     */
    public function pay(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('view-invoice', $invoice);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
        ]);

        try {
            $payment = $this->payments->record($invoice, (string) $validated['amount'], [
                'method' => Payment::ONLINE,
                'payment_date' => Carbon::today(),
                'payer_name' => Auth::user()->owner->full_name,
                'status' => Payment::PENDING,
                'gateway_reference' => 'GW-'.Str::upper(Str::random(16)),
                'notes' => 'Initiated from the owner portal.',
            ]);
        } catch (BillingException $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }

        return redirect()->route('portal.invoices.show', $invoice)->with(
            'status',
            'Payment '.$payment->payment_reference.' has been initiated and is awaiting confirmation from the payment gateway.',
        );
    }
}
