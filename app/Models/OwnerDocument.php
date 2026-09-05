<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** SRS FR-042 - owner documents. */
#[Fillable(['owner_id', 'title', 'type', 'file_path', 'original_name', 'uploaded_by'])]
class OwnerDocument extends Model
{
    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
