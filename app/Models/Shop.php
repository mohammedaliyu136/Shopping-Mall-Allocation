<?php

namespace App\Models;

use App\Support\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** SRS FR-030 / FR-031 - shop registration and status. */
#[Fillable([
    'mall_id', 'block_id', 'floor_id', 'shop_code', 'shop_number', 'shop_type',
    'size_sqm', 'status', 'annual_rent', 'service_charge', 'other_fees', 'description',
])]
class Shop extends Model
{
    use Auditable;

    // FR-031
    public const AVAILABLE = 'available';

    public const RESERVED = 'reserved';

    public const ALLOCATED = 'allocated';

    public const OCCUPIED = 'occupied';

    public const SUSPENDED = 'suspended';

    public const TRANSFERRED = 'transferred';

    public const CLOSED = 'closed';

    public const STATUSES = [
        self::AVAILABLE, self::RESERVED, self::ALLOCATED,
        self::OCCUPIED, self::SUSPENDED, self::TRANSFERRED, self::CLOSED,
    ];

    protected function casts(): array
    {
        return [
            'size_sqm' => 'decimal:2',
            'annual_rent' => 'decimal:2',
            'service_charge' => 'decimal:2',
            'other_fees' => 'decimal:2',
        ];
    }

    public function mall(): BelongsTo
    {
        return $this->belongsTo(Mall::class);
    }

    public function block(): BelongsTo
    {
        return $this->belongsTo(Block::class);
    }

    public function floor(): BelongsTo
    {
        return $this->belongsTo(Floor::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(Allocation::class);
    }

    /** FR-123 - the permanent ownership history of this shop, newest first. */
    public function ownershipHistory(): HasMany
    {
        return $this->hasMany(Allocation::class)->orderByDesc('allocation_date')->orderByDesc('id');
    }

    public function activeAllocation(): HasOne
    {
        return $this->hasOne(Allocation::class)->whereIn('status', Allocation::OCCUPYING_STATUSES);
    }

    /** FR-032 - a shop already held by a live allocation cannot be allocated again. */
    public function isAllocatable(): bool
    {
        return ! $this->allocations()->whereIn('status', Allocation::OCCUPYING_STATUSES)->exists();
    }

    public function label(): string
    {
        $block = $this->block?->name;

        return $block ? "{$block} - Shop {$this->shop_number}" : "Shop {$this->shop_number}";
    }
}
