<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** SRS FR-090 - billing categories. */
#[Fillable(['name', 'code', 'description', 'is_recurring', 'default_amount', 'is_active'])]
class BillingCategory extends Model
{
    public const ANNUAL_RENT = 'annual-rent';

    public const SERVICE_CHARGE = 'service-charge';

    public const PENALTY = 'penalty';

    protected function casts(): array
    {
        return [
            'is_recurring' => 'boolean',
            'is_active' => 'boolean',
            'default_amount' => 'decimal:2',
        ];
    }

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }
}
