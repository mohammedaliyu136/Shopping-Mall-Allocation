<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** SRS FR-120 - supporting documents attached to a transfer request. */
#[Fillable(['ownership_transfer_id', 'title', 'file_path', 'original_name', 'uploaded_by'])]
class TransferDocument extends Model
{
    public function transfer(): BelongsTo
    {
        return $this->belongsTo(OwnershipTransfer::class, 'ownership_transfer_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
