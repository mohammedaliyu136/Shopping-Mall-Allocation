<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** SRS FR-060 / FR-062 - generated documents carrying a unique verification reference. */
#[Fillable(['allocation_id', 'type', 'document_number', 'file_path', 'issued_at', 'issued_by'])]
class AllocationDocument extends Model
{
    public const LETTER = 'letter';

    public const CARD = 'card';

    protected function casts(): array
    {
        return ['issued_at' => 'datetime'];
    }

    public function allocation(): BelongsTo
    {
        return $this->belongsTo(Allocation::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }
}
