<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** SRS FR-091 - individual fees making up an invoice. */
#[Fillable(['invoice_id', 'billing_category_id', 'description', 'quantity', 'unit_amount', 'amount'])]
class InvoiceItem extends Model
{
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_amount' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function billingCategory(): BelongsTo
    {
        return $this->belongsTo(BillingCategory::class);
    }
}
