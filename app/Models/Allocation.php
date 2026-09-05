<?php

namespace App\Models;

use App\Support\Auditable;
use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** SRS 6.6 - allocation records. These are permanent historical records (FR-123). */
#[Fillable([
    'reference_number', 'mall_id', 'shop_id', 'owner_id', 'allocation_date', 'expiry_date',
    'allocation_type', 'terms', 'status', 'verification_token',
    'annual_rent', 'service_charge', 'other_fees',
    'previous_allocation_id', 'created_by', 'approved_by', 'approved_at',
    'cancelled_at', 'cancellation_reason',
])]
class Allocation extends Model
{
    use Auditable;

    // FR-052
    public const DRAFT = 'draft';

    public const PENDING_APPROVAL = 'pending_approval';

    public const APPROVED = 'approved';

    public const ACTIVE = 'active';

    public const SUSPENDED = 'suspended';

    public const CANCELLED = 'cancelled';

    public const TRANSFERRED = 'transferred';

    public const EXPIRED = 'expired';

    public const STATUSES = [
        self::DRAFT, self::PENDING_APPROVAL, self::APPROVED, self::ACTIVE,
        self::SUSPENDED, self::CANCELLED, self::TRANSFERRED, self::EXPIRED,
    ];

    /**
     * FR-032 - statuses that mean a shop is spoken for. A shop holding an allocation
     * in any of these states cannot be allocated again.
     */
    public const OCCUPYING_STATUSES = [
        self::DRAFT, self::PENDING_APPROVAL, self::APPROVED, self::ACTIVE, self::SUSPENDED,
    ];

    /** FR-053 - the approval chain applied to a submitted allocation. */
    public const APPROVAL_STAGES = [
        1 => 'Allocation Officer',
        2 => 'Mall Manager',
        3 => 'Finance Confirmation',
        4 => 'Final Approval',
    ];

    protected function casts(): array
    {
        return [
            'allocation_date' => 'date',
            'expiry_date' => 'date',
            'approved_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'annual_rent' => 'decimal:2',
            'service_charge' => 'decimal:2',
            'other_fees' => 'decimal:2',
        ];
    }

    public function mall(): BelongsTo
    {
        return $this->belongsTo(Mall::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function previousAllocation(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_allocation_id');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(AllocationApproval::class)->orderBy('level');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(AllocationDocument::class);
    }

    public function cards(): HasMany
    {
        return $this->hasMany(AllocationCard::class)->orderByDesc('id');
    }

    public function activeCard(): HasOne
    {
        return $this->hasOne(AllocationCard::class)->where('status', AllocationCard::ACTIVE);
    }

    public function letter(): HasOne
    {
        return $this->hasOne(AllocationDocument::class)->where('type', AllocationDocument::LETTER);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function transfers(): HasMany
    {
        return $this->hasMany(OwnershipTransfer::class);
    }

    public function isLive(): bool
    {
        return in_array($this->status, self::OCCUPYING_STATUSES, true);
    }

    /** FR-081 / FR-083 - what the public verification portal reports. */
    public function verificationStatus(): string
    {
        return match ($this->status) {
            self::ACTIVE => 'VERIFIED',
            self::APPROVED => 'VERIFIED - AWAITING ACTIVATION',
            self::SUSPENDED => 'SUSPENDED',
            self::CANCELLED => 'CANCELLED',
            self::TRANSFERRED => 'TRANSFERRED TO A NEW OWNER',
            self::EXPIRED => 'EXPIRED',
            default => 'NOT YET ISSUED',
        };
    }

    public function isVerified(): bool
    {
        return in_array($this->status, [self::ACTIVE, self::APPROVED], true);
    }

    public function totalAnnualCharges(): string
    {
        return Money::add(Money::add($this->annual_rent, $this->service_charge), $this->other_fees);
    }
}
