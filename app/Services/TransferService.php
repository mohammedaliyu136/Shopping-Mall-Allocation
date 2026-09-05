<?php

namespace App\Services;

use App\Exceptions\TransferException;
use App\Models\Allocation;
use App\Models\AllocationCard;
use App\Models\Owner;
use App\Models\OwnershipTransfer;
use App\Models\Shop;
use App\Models\User;
use App\Support\Money;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * SRS 6.13 - ownership transfers.
 *
 * A transfer never edits or deletes the outgoing allocation. The old allocation
 * is closed with status "transferred" and a fresh allocation is created for the
 * new owner, linked back to it, so the shop keeps a complete ownership history
 * (FR-123).
 */
class TransferService
{
    public function __construct(
        private readonly ReferenceGenerator $references,
        private readonly AllocationService $allocations,
        private readonly BillingService $billing,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * FR-120 - raise a transfer request, capturing the outstanding balance at
     * the moment of the request (FR-121).
     */
    public function request(Allocation $allocation, Owner $newOwner, ?string $reason = null): OwnershipTransfer
    {
        if (! in_array($allocation->status, [Allocation::ACTIVE, Allocation::SUSPENDED], true)) {
            throw TransferException::allocationNotTransferable($allocation->status);
        }

        if ($allocation->owner_id === $newOwner->getKey()) {
            throw TransferException::sameOwner();
        }

        return DB::transaction(function () use ($allocation, $newOwner, $reason) {
            $outstanding = $this->billing->outstandingFor($allocation->owner);

            $transfer = OwnershipTransfer::create([
                'reference_number' => $this->references->transferReference(),
                'shop_id' => $allocation->shop_id,
                'allocation_id' => $allocation->getKey(),
                'from_owner_id' => $allocation->owner_id,
                'to_owner_id' => $newOwner->getKey(),
                'reason' => $reason,
                'status' => OwnershipTransfer::PENDING,
                'outstanding_at_request' => $outstanding,
                'obligations_cleared' => Money::isSettled($outstanding),
                'requested_by' => Auth::id(),
            ]);

            return $transfer->fresh(['fromOwner', 'toOwner', 'shop']);
        });
    }

    /**
     * FR-121 - re-check outstanding obligations at approval time, not just at
     * request time, since bills may have been raised or settled since.
     */
    public function outstandingObligations(OwnershipTransfer $transfer): string
    {
        return $this->billing->outstandingFor($transfer->fromOwner);
    }

    /**
     * FR-122 / FR-124 - approve a transfer. Closes the outgoing allocation and
     * issues a new allocation, letter, card and QR verification record for the
     * incoming owner.
     */
    public function approve(OwnershipTransfer $transfer, User $approver): OwnershipTransfer
    {
        if ($transfer->status !== OwnershipTransfer::PENDING) {
            throw TransferException::alreadyDecided($transfer);
        }

        // FR-121 - obligations must be settled before ownership changes hands.
        $outstanding = $this->outstandingObligations($transfer);

        if (Money::isPositive($outstanding)) {
            throw TransferException::outstandingObligations($transfer, $outstanding);
        }

        return DB::transaction(function () use ($transfer, $approver, $outstanding) {
            $oldAllocation = $transfer->allocation;

            // The outgoing allocation is closed, never deleted (FR-123).
            $oldAllocation->update([
                'status' => Allocation::TRANSFERRED,
                'cancelled_at' => Carbon::now(),
                'cancellation_reason' => 'Transferred under '.$transfer->reference_number,
            ]);

            $oldAllocation->cards()->where('status', AllocationCard::ACTIVE)
                ->update(['status' => AllocationCard::TRANSFERRED]);

            $shop = $oldAllocation->shop;
            $shop->update(['status' => Shop::TRANSFERRED]);

            // FR-124 - a brand new allocation for the incoming owner.
            $newAllocation = $this->allocations->create(
                $shop->fresh(),
                $transfer->toOwner,
                [
                    'allocation_date' => Carbon::today(),
                    'expiry_date' => $oldAllocation->expiry_date,
                    'allocation_type' => $oldAllocation->allocation_type,
                    'terms' => $oldAllocation->terms,
                    'annual_rent' => $oldAllocation->annual_rent,
                    'service_charge' => $oldAllocation->service_charge,
                    'other_fees' => $oldAllocation->other_fees,
                    'previous_allocation_id' => $oldAllocation->getKey(),
                ],
            );

            // The transfer itself is the approval, so the new allocation goes
            // straight to approved and its documents are issued.
            $newAllocation->update([
                'status' => Allocation::APPROVED,
                'approved_by' => $approver->getKey(),
                'approved_at' => Carbon::now(),
            ]);

            $this->allocations->issueDocuments($newAllocation->refresh(), $approver);
            $this->allocations->activate($newAllocation->refresh());

            $transfer->update([
                'status' => OwnershipTransfer::APPROVED,
                'approved_by' => $approver->getKey(),
                'approved_at' => Carbon::now(),
                'obligations_cleared' => true,
                'outstanding_at_request' => $outstanding,
                'new_allocation_id' => $newAllocation->getKey(),
            ]);

            $this->notifications->transferUpdated($transfer->fresh(['fromOwner', 'toOwner', 'shop']));

            return $transfer->fresh(['newAllocation', 'allocation', 'fromOwner', 'toOwner']);
        });
    }

    public function reject(OwnershipTransfer $transfer, User $approver, string $reason): OwnershipTransfer
    {
        if ($transfer->status !== OwnershipTransfer::PENDING) {
            throw TransferException::alreadyDecided($transfer);
        }

        $transfer->update([
            'status' => OwnershipTransfer::REJECTED,
            'approved_by' => $approver->getKey(),
            'approved_at' => Carbon::now(),
            'rejection_reason' => $reason,
        ]);

        $this->notifications->transferUpdated($transfer->fresh(['fromOwner', 'toOwner', 'shop']));

        return $transfer->fresh();
    }

    /**
     * FR-123 - the full ownership history of a shop, newest first. Every
     * allocation ever raised against the shop is retained.
     *
     * @return Collection<int, Allocation>
     */
    public function ownershipHistory(Shop $shop)
    {
        return $shop->ownershipHistory()->with(['owner', 'previousAllocation'])->get();
    }
}
