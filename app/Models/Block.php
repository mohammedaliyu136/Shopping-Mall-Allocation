<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** SRS FR-021 - mall structure. */
#[Fillable(['mall_id', 'name', 'code', 'description', 'status'])]
class Block extends Model
{
    public function mall(): BelongsTo
    {
        return $this->belongsTo(Mall::class);
    }

    public function floors(): HasMany
    {
        return $this->hasMany(Floor::class);
    }

    public function shops(): HasMany
    {
        return $this->hasMany(Shop::class);
    }
}
