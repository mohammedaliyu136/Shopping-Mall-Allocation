<?php

namespace App\Models;

use App\Support\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/** SRS FR-091 to FR-094 - invoices, outstanding balances and overdue detection. */
#[Fillable([
    'invoice_number', 'allocation_id', 'owner_id', 'shop_id',
    'billing_period_start', 'billing_period_end', 'issue_date', 'due_date',
    'total_amount', 'amount_paid', 'balance', 'status', 'overdue_notified_at', 'notes', 'created_by',
])]
class Invoice extends Model
{
    use Auditable;

    public const UNPAID = 'unpaid';

    public const PARTIALLY_PAID = 'partially_paid';

    public const PAID = 'paid';

    public const OVERDUE = 'overdue';

    public const CANCELLED = 'cancelled';

    /** Statuses that still owe money. */
    public const OPEN_STATUSES = [self::UNPAID, self::PARTIALLY_PAID, self::OVERDUE];

    protected function casts(): array
    {
        return [
            'billing_period_start' => 'date',
            'billing_period_end' => 'date',
            'issue_date' => 'date',
            'due_date' => 'date',
            'overdue_notified_at' => 'datetime',
            'total_amount' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'balance' => 'decimal:2',
        ];
    }

    public function allocation(): BelongsTo
    {
        return $this->belongsTo(Allocation::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function confirmedPayments(): HasMany
    {
        return $this->hasMany(Payment::class)->where('status', Payment::CONFIRMED);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** FR-094 - invoices past their due date that still carry a balance. */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->whereIn('status', self::OPEN_STATUSES)
            ->whereDate('due_date', '<', Carbon::today());
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', self::OPEN_STATUSES);
    }

    public function isOverdue(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true)
            && $this->due_date->isBefore(Carbon::today());
    }
}
