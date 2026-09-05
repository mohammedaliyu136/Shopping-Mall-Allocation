<?php

namespace App\Models;

use App\Support\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** SRS FR-100 to FR-103 - payment recording, methods and gateway reconciliation. */
#[Fillable([
    'payment_reference', 'invoice_id', 'owner_id', 'amount', 'payment_date', 'method',
    'payer_name', 'received_by', 'status', 'gateway_reference', 'notes',
])]
class Payment extends Model
{
    use Auditable;

    // FR-101
    public const CASH = 'cash';

    public const BANK_TRANSFER = 'bank_transfer';

    public const POS = 'pos';

    public const ONLINE = 'online';

    public const METHODS = [self::CASH, self::BANK_TRANSFER, self::POS, self::ONLINE];

    public const PENDING = 'pending';

    public const CONFIRMED = 'confirmed';

    public const FAILED = 'failed';

    public const REVERSED = 'reversed';

    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function receipt(): HasOne
    {
        return $this->hasOne(Receipt::class);
    }
}
