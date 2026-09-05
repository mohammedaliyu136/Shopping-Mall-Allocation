<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** SRS FR-021 - mall structure. */
#[Fillable(['block_id', 'name', 'code', 'level'])]
class Floor extends Model
{
    public function block(): BelongsTo
    {
        return $this->belongsTo(Block::class);
    }

    public function shops(): HasMany
    {
        return $this->hasMany(Shop::class);
    }
}
