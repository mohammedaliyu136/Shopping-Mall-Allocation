<?php

namespace App\Models;

use App\Support\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** SRS FR-120 to FR-124 - ownership transfer requests and approvals. */
#[Fillable([
    'reference_number', 'shop_id', 'allocation_id', 'from_owner_id', 'to_owner_id', 'reason',
    'status', 'outstanding_at_request', 'obligations_cleared', 'requested_by',
    'approved_by', 'approved_at', 'rejection_reason', 'new_allocation_id',
])]
class OwnershipTransfer extends Model
{
    use Auditable;

    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    public const CANCELLED = 'cancelled';

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'obligations_cleared' => 'boolean',
            'outstanding_at_request' => 'decimal:2',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function allocation(): BelongsTo
    {
        return $this->belongsTo(Allocation::class);
    }

    public function newAllocation(): BelongsTo
    {
        return $this->belongsTo(Allocation::class, 'new_allocation_id');
    }

    public function fromOwner(): BelongsTo
    {
        return $this->belongsTo(Owner::class, 'from_owner_id');
    }

    public function toOwner(): BelongsTo
    {
        return $this->belongsTo(Owner::class, 'to_owner_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(TransferDocument::class);
    }
}
