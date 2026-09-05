<?php

namespace App\Services;

use App\Exceptions\BillingException;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * SRS 6.11 and 6.12 - payment recording, partial payments, gateway
 * reconciliation and receipt generation.
 */
class PaymentService
{
    public function __construct(
        private readonly ReferenceGenerator $references,
        private readonly BillingService $billing,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * FR-100 / FR-102 - record a payment against an invoice. Partial payments are
     * supported and the invoice balance is recalculated automatically.
     *
     * FR-110 - a receipt is issued for every confirmed payment.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function record(Invoice $invoice, string $amount, array $attributes = []): Payment
    {
        if (! Money::isPositive($amount)) {
            throw BillingException::invalidAmount($amount);
        }

        return DB::transaction(function () use ($invoice, $amount, $attributes) {
            // Lock the invoice so two concurrent payments cannot both pass the
            // balance check and overpay it.
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->getKey());

            if (in_array($invoice->status, [Invoice::PAID, Invoice::CANCELLED], true)) {
                throw BillingException::invoiceNotPayable($invoice);
            }

            if (Money::compare($amount, $invoice->balance) > 0) {
                throw BillingException::paymentExceedsBalance($invoice, $amount);
            }

            // FR-103 - never record the same gateway settlement twice.
            $gatewayReference = $attributes['gateway_reference'] ?? null;

            if ($gatewayReference && Payment::query()->where('gateway_reference', $gatewayReference)->exists()) {
                throw BillingException::duplicateGatewayReference($gatewayReference);
            }

            $status = $attributes['status'] ?? Payment::CONFIRMED;

            $payment = Payment::create([
                'payment_reference' => $attributes['payment_reference'] ?? $this->references->paymentReference(),
                'invoice_id' => $invoice->getKey(),
                'owner_id' => $invoice->owner_id,
                'amount' => $amount,
                'payment_date' => $attributes['payment_date'] ?? Carbon::today(),
                'method' => $attributes['method'] ?? Payment::CASH,
                'payer_name' => $attributes['payer_name'] ?? $invoice->owner?->full_name,
                'received_by' => $attributes['received_by'] ?? Auth::id(),
                'status' => $status,
                'gateway_reference' => $gatewayReference,
                'notes' => $attributes['notes'] ?? null,
            ]);

            $this->billing->recalculate($invoice);

            if ($status === Payment::CONFIRMED) {
                $this->issueReceipt($payment);
                // FR-133 - confirm receipt of payment to the owner.
                $this->notifications->paymentReceived($payment->fresh(['owner', 'invoice.shop', 'receipt']));
            }

            return $payment->fresh(['receipt', 'invoice']);
        });
    }

    /**
     * FR-103 - confirm a pending online payment once the gateway reports success.
     */
    public function confirm(Payment $payment, ?string $gatewayReference = null): Payment
    {
        if ($payment->status === Payment::CONFIRMED) {
            return $payment;
        }

        return DB::transaction(function () use ($payment, $gatewayReference) {
            if ($gatewayReference) {
                $duplicate = Payment::query()
                    ->where('gateway_reference', $gatewayReference)
                    ->whereKeyNot($payment->getKey())
                    ->exists();

                if ($duplicate) {
                    throw BillingException::duplicateGatewayReference($gatewayReference);
                }
            }

            $payment->update([
                'status' => Payment::CONFIRMED,
                'gateway_reference' => $gatewayReference ?? $payment->gateway_reference,
            ]);

            $this->billing->recalculate($payment->invoice);
            $this->issueReceipt($payment);
            $this->notifications->paymentReceived($payment->fresh(['owner', 'invoice.shop', 'receipt']));

            return $payment->fresh(['receipt', 'invoice']);
        });
    }

    /**
     * Reverse a payment that was recorded in error. The payment record is kept
     * for audit purposes and the invoice balance is restored.
     */
    public function reverse(Payment $payment, string $reason): Payment
    {
        return DB::transaction(function () use ($payment, $reason) {
            $payment->update(['status' => Payment::REVERSED, 'notes' => $reason]);
            $this->billing->recalculate($payment->invoice);
            $payment->writeAuditLog('payment.reversed', [], ['reason' => $reason]);

            return $payment->fresh('invoice');
        });
    }

    /** FR-110 - generate the receipt for a confirmed payment, once. */
    public function issueReceipt(Payment $payment, ?User $issuer = null): Receipt
    {
        return Receipt::firstOrCreate(
            ['payment_id' => $payment->getKey()],
            [
                'receipt_number' => $this->references->receiptNumber(),
                'issued_at' => Carbon::now(),
                'issued_by' => $issuer?->getKey() ?? $payment->received_by ?? Auth::id(),
            ],
        );
    }
}
