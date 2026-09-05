<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** SRS FR-070 to FR-072 - allocation cards and their reissue history. */
#[Fillable([
    'allocation_id', 'card_number', 'issue_date', 'expiry_date', 'status',
    'reissue_of_id', 'reissue_reason', 'issued_by',
])]
class AllocationCard extends Model
{
    // FR-071
    public const ACTIVE = 'active';

    public const EXPIRED = 'expired';

    public const SUSPENDED = 'suspended';

    public const CANCELLED = 'cancelled';

    public const TRANSFERRED = 'transferred';

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'expiry_date' => 'date',
        ];
    }

    public function allocation(): BelongsTo
    {
        return $this->belongsTo(Allocation::class);
    }

    public function reissueOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reissue_of_id');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }
}
