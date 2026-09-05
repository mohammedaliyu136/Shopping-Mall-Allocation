<?php

namespace App\Exceptions;

use App\Models\Allocation;
use App\Models\Shop;
use RuntimeException;

/** Domain failures raised by the allocation lifecycle (SRS 6.6). */
class AllocationException extends RuntimeException
{
    /** FR-032 - duplicate allocation of a commercial space is not permitted. */
    public static function shopAlreadyAllocated(Shop $shop, Allocation $existing): self
    {
        return new self(sprintf(
            'Shop %s is already allocated under reference %s (%s). Cancel or transfer that allocation first.',
            $shop->shop_number,
            $existing->reference_number,
            $existing->status,
        ));
    }

    public static function shopNotAvailable(Shop $shop): self
    {
        return new self(sprintf(
            'Shop %s cannot be allocated while its status is "%s".',
            $shop->shop_number,
            $shop->status,
        ));
    }

    public static function invalidTransition(string $from, string $to): self
    {
        return new self("An allocation with status \"{$from}\" cannot move to \"{$to}\".");
    }

    public static function nothingToApprove(Allocation $allocation): self
    {
        return new self("Allocation {$allocation->reference_number} has no pending approval stage.");
    }
}
