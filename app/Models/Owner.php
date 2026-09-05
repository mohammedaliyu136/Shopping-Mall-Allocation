<?php

namespace App\Models;

use App\Support\Auditable;
use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** SRS FR-040 to FR-043 - owner registration, business details and search. */
#[Fillable([
    'owner_code', 'full_name', 'gender', 'date_of_birth', 'photo_path', 'nationality',
    'phone', 'email', 'address', 'id_type', 'id_number',
    'nok_name', 'nok_relationship', 'nok_phone', 'nok_address',
    'business_name', 'business_reg_number', 'business_address', 'business_nature', 'status',
])]
class Owner extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return ['date_of_birth' => 'date'];
    }

    public function documents(): HasMany
    {
        return $this->hasMany(OwnerDocument::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(Allocation::class);
    }

    public function activeAllocations(): HasMany
    {
        return $this->hasMany(Allocation::class)->whereIn('status', Allocation::OCCUPYING_STATUSES);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }

    public function notificationRecipients(): HasMany
    {
        return $this->hasMany(NotificationRecipient::class);
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'owner_id');
    }

    /** FR-093 - outstanding balance across all unsettled invoices. */
    public function outstandingBalance(): string
    {
        return Money::of(
            $this->invoices()->whereIn('status', Invoice::OPEN_STATUSES)->sum('balance')
        );
    }

    /**
     * FR-043 - search owners by name, phone, email, shop number or allocation reference.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function (Builder $q) use ($like) {
            $q->where('full_name', 'like', $like)
                ->orWhere('owner_code', 'like', $like)
                ->orWhere('phone', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhere('business_name', 'like', $like)
                ->orWhereHas('allocations', function (Builder $a) use ($like) {
                    $a->where('reference_number', 'like', $like)
                        ->orWhereHas('shop', fn (Builder $s) => $s->where('shop_number', 'like', $like));
                });
        });
    }
}
