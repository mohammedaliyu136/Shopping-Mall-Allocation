<?php

namespace App\Exceptions;

use App\Models\OwnershipTransfer;
use RuntimeException;

/** Domain failures raised by ownership transfers (SRS 6.13). */
class TransferException extends RuntimeException
{
    /** FR-121 - outstanding obligations block approval. */
    public static function outstandingObligations(OwnershipTransfer $transfer, string $balance): self
    {
        return new self(sprintf(
            'Transfer %s cannot be approved: the current owner has an outstanding balance of %s.',
            $transfer->reference_number,
            $balance,
        ));
    }

    public static function allocationNotTransferable(string $status): self
    {
        return new self("An allocation with status \"{$status}\" cannot be transferred.");
    }

    public static function alreadyDecided(OwnershipTransfer $transfer): self
    {
        return new self("Transfer {$transfer->reference_number} has already been {$transfer->status}.");
    }

    public static function sameOwner(): self
    {
        return new self('A shop cannot be transferred to its current owner.');
    }
}
