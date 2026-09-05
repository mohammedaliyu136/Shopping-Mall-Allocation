<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** SRS FR-020 - mall registration. */
#[Fillable(['name', 'code', 'address', 'description', 'phone', 'email', 'logo_path', 'status'])]
class Mall extends Model
{
    public function blocks(): HasMany
    {
        return $this->hasMany(Block::class);
    }

    public function shops(): HasMany
    {
        return $this->hasMany(Shop::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(Allocation::class);
    }
}
