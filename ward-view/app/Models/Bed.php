<?php

namespace App\Models;

use App\Enums\EncounterStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Bed extends Model
{
    protected $fillable = ['ward_id', 'label', 'group_label', 'position', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class);
    }

    public function occupant(): HasOne
    {
        return $this->hasOne(Encounter::class)->whereIn('status', EncounterStatus::active());
    }
}
