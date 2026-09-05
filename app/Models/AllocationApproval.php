<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** SRS FR-053 - multi-level allocation approval workflow. */
#[Fillable(['allocation_id', 'level', 'stage', 'approver_id', 'status', 'comment', 'acted_at'])]
class AllocationApproval extends Model
{
    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    protected function casts(): array
    {
        return ['acted_at' => 'datetime'];
    }

    public function allocation(): BelongsTo
    {
        return $this->belongsTo(Allocation::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}
