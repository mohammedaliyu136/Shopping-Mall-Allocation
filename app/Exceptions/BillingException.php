<?php

namespace App\Exceptions;

use App\Models\Invoice;
use RuntimeException;

/** Domain failures raised by billing and payment handling (SRS 6.10, 6.11). */
class BillingException extends RuntimeException
{
    public static function invoiceNotPayable(Invoice $invoice): self
    {
        return new self("Invoice {$invoice->invoice_number} is {$invoice->status} and cannot accept a payment.");
    }

    public static function paymentExceedsBalance(Invoice $invoice, string $amount): self
    {
        return new self(sprintf(
            'Payment of %s exceeds the outstanding balance of %s on invoice %s.',
            $amount,
            $invoice->balance,
            $invoice->invoice_number,
        ));
    }

    /** FR-103 - the gateway reference guards against recording a payment twice. */
    public static function duplicateGatewayReference(string $reference): self
    {
        return new self("A payment with gateway reference \"{$reference}\" has already been recorded.");
    }

    public static function invalidAmount(string $amount): self
    {
        return new self("Payment amount \"{$amount}\" must be greater than zero.");
    }
}
