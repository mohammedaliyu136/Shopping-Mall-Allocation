<?php

namespace App\Services;

use App\Exceptions\AllocationException;
use App\Models\Allocation;
use App\Models\AllocationApproval;
use App\Models\AllocationCard;
use App\Models\AllocationDocument;
use App\Models\Owner;
use App\Models\Shop;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * SRS 6.6 to 6.9 - the allocation lifecycle: create, approve, activate, cancel,
 * and the generation of allocation letters, cards and QR verification records.
 */
class AllocationService
{
    public function __construct(
        private readonly ReferenceGenerator $references,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * FR-050 - allocate an available shop to an owner.
     *
     * FR-032 - refuses to allocate a shop that already carries a live allocation.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(Shop $shop, Owner $owner, array $attributes = []): Allocation
    {
        return DB::transaction(function () use ($shop, $owner, $attributes) {
            // Re-read the shop inside the transaction so two concurrent requests
            // cannot both pass the availability check (FR-032).
            $shop = Shop::query()->lockForUpdate()->findOrFail($shop->getKey());

            $this->assertShopIsAllocatable($shop);

            if (in_array($shop->status, [Shop::SUSPENDED, Shop::CLOSED], true)) {
                throw AllocationException::shopNotAvailable($shop);
            }

            $allocationDate = isset($attributes['allocation_date'])
                ? Carbon::parse($attributes['allocation_date'])
                : Carbon::today();

            $allocation = Allocation::create([
                'reference_number' => $this->references->allocationReference($shop, $allocationDate),
                'verification_token' => $this->references->verificationToken(),
                'mall_id' => $shop->mall_id,
                'shop_id' => $shop->getKey(),
                'owner_id' => $owner->getKey(),
                'allocation_date' => $allocationDate,
                'expiry_date' => $attributes['expiry_date'] ?? null,
                'allocation_type' => $attributes['allocation_type'] ?? 'lease',
                'terms' => $attributes['terms'] ?? SystemSetting::get('allocation.default_terms'),
                'status' => Allocation::DRAFT,
                'annual_rent' => $attributes['annual_rent'] ?? $shop->annual_rent,
                'service_charge' => $attributes['service_charge'] ?? $shop->service_charge,
                'other_fees' => $attributes['other_fees'] ?? $shop->other_fees,
                'previous_allocation_id' => $attributes['previous_allocation_id'] ?? null,
                'created_by' => Auth::id(),
            ]);

            $shop->update(['status' => Shop::RESERVED]);

            return $allocation->fresh(['shop', 'owner', 'mall']);
        });
    }

    /** FR-032 - the guard used before creating or transferring into an allocation. */
    public function assertShopIsAllocatable(Shop $shop): void
    {
        $existing = $shop->allocations()
            ->whereIn('status', Allocation::OCCUPYING_STATUSES)
            ->first();

        if ($existing) {
            throw AllocationException::shopAlreadyAllocated($shop, $existing);
        }
    }

    /**
     * FR-053 - submit a draft for the multi-level approval workflow.
     */
    public function submitForApproval(Allocation $allocation): Allocation
    {
        if ($allocation->status !== Allocation::DRAFT) {
            throw AllocationException::invalidTransition($allocation->status, Allocation::PENDING_APPROVAL);
        }

        return DB::transaction(function () use ($allocation) {
            $allocation->update(['status' => Allocation::PENDING_APPROVAL]);

            $stages = $this->approvalStages();

            foreach ($stages as $level => $stage) {
                AllocationApproval::firstOrCreate(
                    ['allocation_id' => $allocation->getKey(), 'level' => $level],
                    ['stage' => $stage, 'status' => AllocationApproval::PENDING],
                );
            }

            return $allocation->fresh('approvals');
        });
    }

    /**
     * FR-053 - record one approval decision. Once every level has approved, the
     * allocation is approved and its documents are generated (FR-060, FR-070, FR-080).
     */
    public function approve(Allocation $allocation, User $approver, ?string $comment = null): Allocation
    {
        if ($allocation->status !== Allocation::PENDING_APPROVAL) {
            throw AllocationException::invalidTransition($allocation->status, Allocation::APPROVED);
        }

        return DB::transaction(function () use ($allocation, $approver, $comment) {
            $pending = $allocation->approvals()
                ->where('status', AllocationApproval::PENDING)
                ->orderBy('level')
                ->first();

            if (! $pending) {
                throw AllocationException::nothingToApprove($allocation);
            }

            $pending->update([
                'status' => AllocationApproval::APPROVED,
                'approver_id' => $approver->getKey(),
                'comment' => $comment,
                'acted_at' => Carbon::now(),
            ]);

            $outstanding = $allocation->approvals()
                ->where('status', AllocationApproval::PENDING)
                ->exists();

            if ($outstanding) {
                return $allocation->fresh('approvals');
            }

            $allocation->update([
                'status' => Allocation::APPROVED,
                'approved_by' => $approver->getKey(),
                'approved_at' => Carbon::now(),
            ]);

            // FR-060 / FR-070 / FR-080 - documents are produced on approval.
            $this->issueDocuments($allocation->refresh(), $approver);

            return $allocation->fresh(['approvals', 'documents', 'cards']);
        });
    }

    public function reject(Allocation $allocation, User $approver, string $reason): Allocation
    {
        return DB::transaction(function () use ($allocation, $approver, $reason) {
            $pending = $allocation->approvals()
                ->where('status', AllocationApproval::PENDING)
                ->orderBy('level')
                ->first();

            $pending?->update([
                'status' => AllocationApproval::REJECTED,
                'approver_id' => $approver->getKey(),
                'comment' => $reason,
                'acted_at' => Carbon::now(),
            ]);

            $this->cancel($allocation, $reason);

            return $allocation->fresh('approvals');
        });
    }

    /**
     * FR-052 - an approved allocation becomes active, and the shop becomes occupied.
     */
    public function activate(Allocation $allocation): Allocation
    {
        if ($allocation->status !== Allocation::APPROVED) {
            throw AllocationException::invalidTransition($allocation->status, Allocation::ACTIVE);
        }

        return DB::transaction(function () use ($allocation) {
            $allocation->update(['status' => Allocation::ACTIVE]);
            $allocation->shop->update(['status' => Shop::OCCUPIED]);

            // FR-133 - the owner is told their allocation status changed.
            $this->notifications->allocationActivated($allocation->fresh(['owner', 'shop']));

            return $allocation->fresh();
        });
    }

    /**
     * FR-060 to FR-062, FR-070, FR-080 - issue the allocation letter, the
     * allocation card and the QR verification record for an approved allocation.
     */
    public function issueDocuments(Allocation $allocation, ?User $issuer = null): Allocation
    {
        return DB::transaction(function () use ($allocation, $issuer) {
            $issuerId = $issuer?->getKey() ?? Auth::id();

            // FR-060 allocation letter
            if (! $allocation->documents()->where('type', AllocationDocument::LETTER)->exists()) {
                AllocationDocument::create([
                    'allocation_id' => $allocation->getKey(),
                    'type' => AllocationDocument::LETTER,
                    'document_number' => $this->references->documentNumber(AllocationDocument::LETTER),
                    'issued_at' => Carbon::now(),
                    'issued_by' => $issuerId,
                ]);
            }

            // FR-070 allocation card
            if (! $allocation->cards()->where('status', AllocationCard::ACTIVE)->exists()) {
                $card = AllocationCard::create([
                    'allocation_id' => $allocation->getKey(),
                    'card_number' => $this->references->cardNumber(),
                    'issue_date' => Carbon::today(),
                    'expiry_date' => $allocation->expiry_date,
                    'status' => AllocationCard::ACTIVE,
                    'issued_by' => $issuerId,
                ]);

                AllocationDocument::create([
                    'allocation_id' => $allocation->getKey(),
                    'type' => AllocationDocument::CARD,
                    'document_number' => $this->references->documentNumber(AllocationDocument::CARD),
                    'issued_at' => Carbon::now(),
                    'issued_by' => $issuerId,
                ]);

                $allocation->writeAuditLog('card.issued', [], ['card_number' => $card->card_number]);
            }

            return $allocation->fresh(['documents', 'cards']);
        });
    }

    /**
     * FR-072 - reissue an allocation card, retaining the previous card and the reason.
     */
    public function reissueCard(Allocation $allocation, string $reason, ?User $issuer = null): AllocationCard
    {
        return DB::transaction(function () use ($allocation, $reason, $issuer) {
            $current = $allocation->cards()->where('status', AllocationCard::ACTIVE)->first();

            $current?->update(['status' => AllocationCard::CANCELLED]);

            $card = AllocationCard::create([
                'allocation_id' => $allocation->getKey(),
                'card_number' => $this->references->cardNumber(),
                'issue_date' => Carbon::today(),
                'expiry_date' => $allocation->expiry_date,
                'status' => AllocationCard::ACTIVE,
                'reissue_of_id' => $current?->getKey(),
                'reissue_reason' => $reason,
                'issued_by' => $issuer?->getKey() ?? Auth::id(),
            ]);

            $allocation->writeAuditLog(
                'card.reissued',
                ['card_number' => $current?->card_number],
                ['card_number' => $card->card_number, 'reason' => $reason],
            );

            return $card;
        });
    }

    /**
     * FR-052 / FR-123 - cancelling releases the shop but never deletes the
     * allocation record, which remains part of the shop's history.
     */
    public function cancel(Allocation $allocation, string $reason): Allocation
    {
        if (in_array($allocation->status, [Allocation::CANCELLED, Allocation::TRANSFERRED], true)) {
            throw AllocationException::invalidTransition($allocation->status, Allocation::CANCELLED);
        }

        return DB::transaction(function () use ($allocation, $reason) {
            $allocation->update([
                'status' => Allocation::CANCELLED,
                'cancelled_at' => Carbon::now(),
                'cancellation_reason' => $reason,
            ]);

            $allocation->cards()->where('status', AllocationCard::ACTIVE)
                ->update(['status' => AllocationCard::CANCELLED]);

            $this->releaseShop($allocation->shop);

            $this->notifications->allocationStatusChanged($allocation->fresh(['owner', 'shop']));

            return $allocation->fresh();
        });
    }

    public function suspend(Allocation $allocation, string $reason): Allocation
    {
        if (! in_array($allocation->status, [Allocation::ACTIVE, Allocation::APPROVED], true)) {
            throw AllocationException::invalidTransition($allocation->status, Allocation::SUSPENDED);
        }

        return DB::transaction(function () use ($allocation, $reason) {
            $allocation->update(['status' => Allocation::SUSPENDED]);
            $allocation->cards()->where('status', AllocationCard::ACTIVE)
                ->update(['status' => AllocationCard::SUSPENDED]);
            $allocation->shop->update(['status' => Shop::SUSPENDED]);

            $allocation->writeAuditLog('allocation.suspended', [], ['reason' => $reason]);
            $this->notifications->allocationStatusChanged($allocation->fresh(['owner', 'shop']));

            return $allocation->fresh();
        });
    }

    public function reinstate(Allocation $allocation): Allocation
    {
        if ($allocation->status !== Allocation::SUSPENDED) {
            throw AllocationException::invalidTransition($allocation->status, Allocation::ACTIVE);
        }

        return DB::transaction(function () use ($allocation) {
            $allocation->update(['status' => Allocation::ACTIVE]);
            $allocation->cards()->where('status', AllocationCard::SUSPENDED)
                ->update(['status' => AllocationCard::ACTIVE]);
            $allocation->shop->update(['status' => Shop::OCCUPIED]);

            return $allocation->fresh();
        });
    }

    /** Returns a shop to the pool once no live allocation holds it. */
    public function releaseShop(Shop $shop): void
    {
        $stillHeld = $shop->allocations()
            ->whereIn('status', Allocation::OCCUPYING_STATUSES)
            ->exists();

        if (! $stillHeld) {
            $shop->update(['status' => Shop::AVAILABLE]);
        }
    }

    /**
     * FR-053 - the configured approval chain. Setting the chain to a single
     * stage effectively disables multi-level approval, which the SRS makes optional.
     *
     * @return array<int, string>
     */
    public function approvalStages(): array
    {
        $configured = SystemSetting::get('allocation.approval_stages');

        if (is_array($configured) && $configured !== []) {
            return array_combine(range(1, count($configured)), array_values($configured));
        }

        return Allocation::APPROVAL_STAGES;
    }
}
